<?php namespace lang\ast\syntax\php;

use lang\ast\Code;
use lang\ast\nodes\{Annotation, ArrayLiteral, Literal, InstanceExpression, InvokeExpression, ScopeExpression};
use lang\ast\syntax\Extension;
use lang\ast\types\{IsArray, IsGeneric, IsValue, IsNullable};

class Generics implements Extension {

  /**
   * Returns the component name for a given qualified literal
   *
   * @param  lang.ast.Type $type
   * @return string
   */
  public static function component($type) {
    $literal= $type->literal();
    return substr($literal, strrpos($literal, '\\') + 1);
  }

  /**
   * Returns whether a given parameter is a generic type component
   *
   * @param  lang.ast.Type $type
   * @param  lang.ast.Type[] $components
   * @return ?string
   */
  private static function generic($type, $components) {
    if ($type instanceof IsValue && in_array($type, $components)) {
      return self::component($type);
    } else if ($type instanceof IsNullable) {
      if ($generic= self::generic($type->element, $components)) return '?'.$generic;
    } else if ($type instanceof IsArray) {
      if ($generic= self::generic($type->component, $components)) return $generic.'[]';
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
                  [new ScopeExpression($node->type->base, new Literal('class'))]
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
      return $node;
    });

    $emitter->transform('class', function($codegen, $node) {
      if ($node->name instanceof IsGeneric) {

        // Attach generic annotation on type with components
        self::annotate($node, [[
          new Literal("'self'"),
          new Literal("'".implode(', ', array_map([self::class, 'component'], $node->name->components))."'")
        ]]);

        // Rewrite property types
        foreach ($node->properties() as $property) {
          self::annotate($property, self::property($property, $node->name->components));
        }

        // Rewrite constructor and method signatures
        foreach ($node->methods() as $method) {
          self::annotate($method, self::method($method, $node->name->components));
        }

        // Rewrite class name
        $node->name= new IsValue($node->name->base);
      }
      return $node;
    });
  }
}