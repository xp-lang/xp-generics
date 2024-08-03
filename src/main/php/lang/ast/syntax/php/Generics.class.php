<?php namespace lang\ast\syntax\php;

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
use lang\ast\types\{IsArray, IsFunction, IsGeneric, IsIntersection, IsLiteral, IsMap, IsUnion, IsNullable, IsValue};
use lang\ast\{Type, Code};

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
   * Returns an expression to create a new generic instance from a given type
   *
   * @param  lang.ast.types.IsGeneric $type
   * @param  ?lang.ast.nodes.TypeDeclaration $enclosing
   * @param  string
   */
  public static function typename($type, $enclosing) {
    if (null === $enclosing || $type instanceof IsLiteral) {
      return $type->name();
    } else if ($type instanceof IsGeneric) {
      $components= '';
      foreach ($type->components as $component) {
        $components.= ', '.self::typename($component, $enclosing);
      }
      return self::typename($type->base, $enclosing).'<'.substr($components, 2).'>';
    } else if ($type instanceof IsArray) {
      return self::typename($type->component, $enclosing).'[]';
    } else if ($type instanceof IsMap) {
      return '[:'.self::typename($type->value, $enclosing).']';
    } else if ($type instanceof IsNullable) {
      return '?'.self::typename($type->element, $enclosing);
    } else if ($type instanceof IsUnion) {
      $union= '';
      foreach ($type->components as $component) {
        $union.= '|'.self::typename($component, $enclosing);
      }
      return substr($union, 1);
    } else if ($type instanceof IsIntersection) {
      $intersection= '';
      foreach ($type->components as $component) {
        $intersection.= '&'.self::typename($component, $enclosing);
      }
      return substr($intersection, 1);
    } else if ($type instanceof IsFunction) {
      $parameters= '';
      foreach ($type->signature as $parameter) {
        $parameters.= ', '.self::typename($parameter, $enclosing);
      }
      return '(function('.substr($parameters, 2).'): '.self::typename($type->returns, $enclosing).')';
    } else if ('self' === $type->literal || 'static' === $type->literal) {
      return $enclosing->name->name();
    } else if ('parent' === $type->literal) {
      return $enclosing->parent->name->name();
    } else if (
      $enclosing->name instanceof IsGenericDeclaration &&
      in_array($type->literal(), $enclosing->name->components())
    ) {
      return "'.\${$type->name()}->getName().'";
    } else {
      return $type->name();
    }
  }

  /**
   * Returns node when node needs to be rewritten
   *
   * @param  string|lang.ast.Type $type
   * @param  ?lang.ast.emit.InType $scope
   * @param  ?lang.ast.Node
   */
  public static function rewrite($type, $scope) {
    if ($type instanceof IsGeneric) {
      $name= self::typename($type, $scope->type ?? null);
      return new InvokeExpression(
        new ScopeExpression('\\lang\\Type', new Literal('forName')),
        [new Literal("'{$name}'")]
      );
    }

    // Check for types containing components
    if ($scope) {
      $name= self::typename($type instanceof Type ? $type : new IsValue($type), $scope->type ?? null);
      if (false !== strpos($name, '$')) {
        return new InvokeExpression(
          new ScopeExpression('\\lang\\Type', new Literal('forName')),
          [new Literal("'{$name}'")]
        );
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
   * @param  lang.ast.nodes.TypeDeclaration $type
   * @return lang.ast.Node[][]
   */
  public static function method($method, $type) {
    $r= [];

    // Check all parameter types
    $params= [];
    foreach ($method->signature->parameters as $parameter) {
      if ($parameter->type && false !== strpos(self::typename($parameter->type, $type), '$')) {
        $params[]= $parameter->type->name().($parameter->variadic ? '...' : '');
        $parameter->type= null;
      } else {
        $params[]= '';
      }
    }
    $params && $r[]= [new Literal("'params'"), new Literal("'".implode(', ', $params)."'")];

    // Check return type
    if ($method->signature->returns && false !== strpos(self::typename($method->signature->returns, $type), '$')) {
      $r[]= [new Literal("'return'"), new Literal("'".$method->signature->returns->name()."'")];
      $method->signature->returns= null;
    }

    return $r;
  }

  /**
   * Process a property and returns annotation arguments
   *
   * @param  lang.ast.nodes.Property $property
   * @param  lang.ast.nodes.TypeDeclaration $type
   * @return lang.ast.Node[][]
   */
  public static function property($property, $type) {
    $r= [];

    // Check property type
    if ($property->type && false !== strpos(self::typename($property->type, $type), '$')) {
      $r[]= [new Literal("'var'"), new Literal("'".$property->type->name()."'")];
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
    $type->name= new IsGenericDeclaration($type->name);
    $values[]= [
      new Literal("'self'"),
      new Literal("'".self::components($type->name->components())."'")
    ];

    // Attach generic annotation on type with components
    self::annotate($type, $values);

    // Rewrite property types
    foreach ($type->properties() as $property) {
      self::annotate($property, self::property($property, $type));
    }

    // Rewrite constructor and method signatures
    foreach ($type->methods() as $method) {
      self::annotate($method, self::method($method, $type));
    }

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
      if ($rewrite= self::rewrite($node->type, $codegen->scope[0] ?? null)) {
        return new InvokeExpression(
          new InstanceExpression($rewrite, new Literal('newInstance')),
          $node->arguments
        );
      }

      return $node;
    });

    $emitter->transform('cast', function($codegen, $node) {
      if ($rewrite= self::rewrite($node->type, $codegen->scope[0] ?? null)) {
        return new InvokeExpression(
          new InstanceExpression($rewrite, new Literal('cast')),
          [$node->expression]
        );
      }

      return $node;
    });

    $emitter->transform('instanceof', function($codegen, $node) {
      if ($rewrite= self::rewrite($node->type, $codegen->scope[0] ?? null)) {
        return new InvokeExpression(
          new InstanceExpression($rewrite, new Literal('isInstance')),
          [$node->expression]
        );
      }

      return $node;
    });

    $emitter->transform('scope', function($codegen, $node) {
      if ($rewrite= self::rewrite($node->type, $codegen->scope[0] ?? null)) {
        return new InvokeExpression(
          new InstanceExpression($rewrite, new Literal('literal')),
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
      if ($node->parent && $rewrite= self::rewrite($node->parent, $codegen->scope[0] ?? null)) {
        return [$rewrite, $node];
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
  }
}