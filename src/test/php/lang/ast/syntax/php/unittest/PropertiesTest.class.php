<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\{
  ArrayType,
  MapType,
  Nullable,
  Primitive,
  Reflection
};
use test\{Assert, Test};

class PropertiesTest extends EmittingTest {

  /**
   * Returns type of the given property.
   *
   * @param  lang.Type $type
   * @param  string $method
   * @return lang.Type
   */
  private function propertyType($type, $method) {
    return Reflection::type($type)->property($method)->constraint()->type();
  }

  #[Test]
  public function generic() {
    $t= $this->type('class %T<E> {
      public E $element;
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      $c,
      $this->propertyType($t->newGenericType([$c]), 'element')
    );
  }

  #[Test]
  public function nullable_generic() {
    $t= $this->type('class %T<E> {
      public ?E $element;
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new Nullable($c),
      $this->propertyType($t->newGenericType([$c]), 'element')
    );
  }

  #[Test]
  public function array_of_generic() {
    $t= $this->type('class %T<E> {
      public array<E> $elements;
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new ArrayType($c),
      $this->propertyType($t->newGenericType([$c]), 'elements')
    );
  }

  #[Test]
  public function map_of_generic() {
    $t= $this->type('class %T<K, V> {
      public array<K, V> $pairs;
    }');

    $v= Primitive::$INT;
    Assert::equals(
      new MapType($v),
      $this->propertyType($t->newGenericType([Primitive::$STRING, $v]), 'pairs')
    );
  }
}