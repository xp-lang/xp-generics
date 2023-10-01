<?php namespace lang\ast\syntax\php;

use lang\ast\Code;
use lang\ast\nodes\{
  Annotation,
  ArrayLiteral,
  InstanceExpression,
  InvokeExpression,
  Literal,
  ScopeExpression,
  TernaryExpression,
  Variable
};
use lang\ast\syntax\Extension;
use lang\ast\types\{IsArray, IsFunction, IsGeneric, IsMap, IsUnion, IsNullable, IsValue};

/**
 * XP Generics extensions
 *
 * @see   https://github.com/xp-framework/rfc/issues/106
 * @see   https://github.com/xp-framework/rfc/issues/193
 * @test  lang.ast.syntax.php.unittest.GenericsTest
 * @test  lang.ast.syntax.php.unittest.CastingTest
 * @test  lang.ast.syntax.php.unittest.PropertiesTest
 * @test  lang.ast.syntax.php.unittest.MethodsTest
 */
class Generics implements Extension {

  /**
   * Returns the component name for a given type
   *
   * @param  lang.ast.Type $type
   * @return string
   */
  public static function component($type) {
    $literal= $type->literal();
    return substr($literal, strrpos($literal, '\\') + 1);
  }

  /**
   * Returns the component list for a given type list
   *
   * @param  lang.ast.Type[] $type
   * @return string
   */
  public static function components($types) {
    $list= '';
    foreach ($types as $type) {
      $literal= $type->literal();
      $list.= ', '.substr($literal, strrpos($literal, '\\') + 1);
    }
    return substr($list, 2);
  }

  /**
   * Returns whether a given list contains a generic component
   *
   * @param  lang.ast.Type[] $list
   * @param  lang.ast.Type[] $components
   * @param  string $prefix
   * @param  string $suffix
   * @return ?string
   */
  private static function generics($list, $components, $prefix= '', $suffix= '') {
    $contained= false;
    $generics= [];
    foreach ($list as $type) {
      if ($generic= self::generic($type, $components, $prefix, $suffix)) {
        $contained= true;
        $generics[]= $generic;
      } else {
        $generics[]= $type ? $type->literal() : 'var';
      }
    }
    return $contained ? $generics : null;
  }

  /**
   * Returns whether a given type is a generic component
   *
   * @param  lang.ast.Type $type
   * @param  lang.ast.Type[] $components
   * @param  string $prefix
   * @param  string $suffix
   * @return ?string
   */
  private static function generic($type, $components, $prefix= '', $suffix= '') {
    if ($type instanceof IsValue && in_array($type, $components)) {
      return $prefix.self::component($type).$suffix;
    } else if ($type instanceof IsNullable) {
      if ($generic= self::generic($type->element, $components, $prefix, $suffix)) return '?'.$generic;
    } else if ($type instanceof IsArray) {
      if ($generic= self::generic($type->component, $components, $prefix, $suffix)) return $generic.'[]';
    } else if ($type instanceof IsMap) {
      if ($generic= self::generic($type->value, $components, $prefix, $suffix)) return '[:'.$generic.']';
    } else if ($type instanceof IsUnion) {
      if ($generic= self::generics($type->components, $components, $prefix, $suffix)) return implode('|', $generic);
    } else if ($type instanceof IsGeneric) {
      if ($generic= self::generics($type->components, $components, $prefix, $suffix)) {
        return $type->base->name().'<'.implode(', ', $generic).'>';
      }
    } else if ($type instanceof IsFunction) {
      if ($generic= self::generics(array_merge([$type->returns], $type->signature), $components, $prefix, $suffix)) {
        $return= array_shift($generic);
        return '(function('.implode(', ', $generic).'): '.$return.')';
      }
    }
    return null;
  }

  /**
   * Add a `lang.Generic` annotation
   * 
   * @param  lang.ast.nodes.Annotated $annotated
   * @param  lang.ast.Node[][] $arguments
   */
  public static function annotate($annotated, $arguments) {
    $arguments && $annotated->annotate(new Annotation('lang\\Generic', [new ArrayLiteral($arguments)]));
  }

  /**
   * Process a method and returns annotation arguments
   *
   * @param  lang.ast.nodes.Method $method
   * @param  string[] $components
   * @return lang.ast.Node[][]
   */
  public static function method($method, $components) {
    $r= [];

    // Check all parameter types
    $params= [];
    foreach ($method->signature->parameters as $parameter) {
      if ($parameter->type && ($generic= self::generic($parameter->type, $components))) {
        $params[]= $generic.($parameter->variadic ? '...' : '');
        $parameter->type= null;
      }
    }
    $params && $r[]= [new Literal("'params'"), new Literal("'".implode(', ', $params)."'")];

    // Check return type
    if ($method->signature->returns && ($generic= self::generic($method->signature->returns, $components))) {
      $r[]= [new Literal("'return'"), new Literal("'".$generic."'")];
      $method->signature->returns= null;
    }

    return $r;
  }

  /**
   * Process a property and returns annotation arguments
   *
   * @param  lang.ast.nodes.Property $property
   * @param  string[] $components
   * @return lang.ast.Node[][]
   */
  public static function property($property, $components) {
    $r= [];

    // Check property type
    if ($property->type && ($generic= self::generic($property->type, $components))) {
      $r[]= [new Literal("'var'"), new Literal("'".$generic."'")];
      $property->type= null;
    }

    return $r;
  }

  /**
   * Process a type
   *
   * @param  lang.ast.nodes.TypeDeclaration $type
   * @param  lang.ast.Node[][] $values
   * @return lang.ast.nodes.TypeDeclaration
   */
  public static function type($type, $values) {
    $values[]= [
      new Literal("'self'"),
      new Literal("'".self::components($type->name->components)."'")
    ];

    // Attach generic annotation on type with components
    self::annotate($type, $values);

    // Rewrite property types
    foreach ($type->properties() as $property) {
      self::annotate($property, self::property($property, $type->name->components));
    }

    // Rewrite constructor and method signatures
    foreach ($type->methods() as $method) {
      self::annotate($method, self::method($method, $type->name->components));
    }

    // Ensure class name is emitted as its base type
    $type->name= new IsGenericDeclaration($type->name);
    return $type;
  }

  /**
   * Setup this extension
   * 
   * @param  lang.ast.Language $language
   * @param  lang.ast.Emitter $emitter
   */
  public function setup($language, $emitter) {
    $emitter->transform('new', function($codegen, $node) {
      if ($node->type instanceof IsGeneric) {

        // Call Type::forName() for each generic type arg
        $typeargs= [];
        foreach ($node->type->components as $type) {
          $typeargs[]= [null, new InvokeExpression(
            new ScopeExpression('\\lang\\Type', new Literal('forName')),
            [new Literal("'".$type->literal()."'")]
          )];
        }

        // XPClass::forName(T::class)->newGenericType($typeargs)->newInstance(...)
        return new InvokeExpression(
          new InstanceExpression(
            new InvokeExpression(
              new InstanceExpression(
                new InvokeExpression(
                  new ScopeExpression('\\lang\\XPClass', new Literal('forName')),
                  [new ScopeExpression($node->type->base->literal(), new Literal('class'))]
                ),
                new Literal('newGenericType')
              ),
              [new ArrayLiteral($typeargs)]
            ),
            new Literal('newInstance')
          ),
          $node->arguments
        );
      }

      // Rewrite `new T(...)` -> `$T->newInstance(...)` if T is a component
      if (
        $node->type instanceof IsValue &&
        $generic= self::generic($node->type, $codegen->scope[0]->type->name->components())
      ) {
        return new InvokeExpression(
          new InstanceExpression(new Variable($generic), new Literal('newInstance')),
          $node->arguments
        );
      }

      return $node;
    });

    $emitter->transform('instanceof', function($codegen, $node) {

      // Rewrite `... instanceof T` -> `$T->isInstance(...)` if T is a component
      if (
        is_string($node->type) &&
        $generic= self::generic(new IsValue($node->type), $codegen->scope[0]->type->name->components())
      ) {
        return new InvokeExpression(
          new InstanceExpression(new Variable($generic), new Literal('isInstance')),
          [$node->expression]
        );
      }

      return $node;
    });

    $emitter->transform('scope', function($codegen, $node) {

      // Rewrite `T::class` to `$T->literal()` if T is a component
      if (
        $node->member instanceof Literal &&
        'class' === $node->member->expression &&
        $generic= self::generic(new IsValue($node->type), $codegen->scope[0]->type->name->components())
      ) {
        return new InvokeExpression(
          new InstanceExpression(new Variable($generic), new Literal('literal')),
          []
        );
      }

      return $node;
    });

    $emitter->transform('class', function($codegen, $node) {
      if ($node->name instanceof IsGeneric) {
        $values= [];

        // Rewrite if parent class is generic
        if ($node->parent instanceof IsGeneric) {
          $values[]= [
            new Literal("'parent'"),
            new Literal("'".self::components($node->parent->components)."'")
          ];
          $node->parent= $node->parent->base;
        }

        // Rewrite if any of the interfaces is generic
        $implements= [0, []];
        foreach ($node->implements as $i => &$interface) {
          if ($interface instanceof IsGeneric) {
            $implements[1][]= [null, new Literal("'".self::components($interface->components)."'")];
            $implements[0]= true;
            $interface= $interface->base;
          } else {
            $implements[1][]= [null, new Literal('null')];
          }
        }
        $implements[0] && $values[]= [new Literal("'implements'"), new ArrayLiteral($implements[1])];

        return self::type($node, $values);
      }

      // Extend generic parent with type arguments. Ensure parent class
      // is created via newGenericType() before extending it.
      if ($node->parent instanceof IsGeneric) {
        $typeargs= [];
        foreach ($node->parent->components as $type) {
          $typeargs[]= [null, new InvokeExpression(
            new ScopeExpression('\\lang\\Type', new Literal('forName')),
            [new Literal("'".$type->literal()."'")]
          )];
        }
        return [
          new InvokeExpression(
            new InstanceExpression(
              new InvokeExpression(
                new ScopeExpression('\\lang\\XPClass', new Literal('forName')),
                [new Literal("'".$node->parent->base->literal()."'")]
              ),
              new Literal('newGenericType')
            ),
            [new ArrayLiteral($typeargs)]
          ),
          $node
        ];
      }

      return $node;
    });

    $emitter->transform('interface', function($codegen, $node) {
      if ($node->name instanceof IsGeneric) {
        $values= [];

        // Rewrite if any of the parent interfaces is generic
        $implements= [0, []];
        foreach ($node->parents as $i => &$interface) {
          if ($interface instanceof IsGeneric) {
            $implements[1][]= [null, new Literal("'".self::components($interface->components)."'")];
            $implements[0]= true;
            $interface= $interface->base;
          } else {
            $implements[1][]= [null, new Literal('null')];
          }
        }
        $implements[0] && $values[]= [new Literal("'extends'"), new ArrayLiteral($implements[1])];

        return self::type($node, $values);
      }
      return $node;
    });

    $emitter->transform('cast', function($codegen, $node) {
      $type= $codegen->scope[0]->type;
      if ($type->name instanceof IsGenericDeclaration) {
        if ($generic= self::generic($node->type, $type->name->components(), '{$_G[\'', '\']}')) {
          return new TernaryExpression(
            new Code('($_G ?? $_G= self::$__generic)'),
            new InvokeExpression(new Literal('cast'), [$node->expression, new Literal('"'.$generic.'"')]),
            new Literal('null')
          );
        }
      }
      return $node;
    });
  }
}