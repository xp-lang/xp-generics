<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\reflection\TargetException;
use lang\{ArrayType, TypeUnion, Primitive, Reflection, Runnable, Closeable, XPClass};
use test\{Assert, Expect, Test, Values};

class CastingTest extends EmittingTest {

  /**
   * Invokes static fixture method with the given arguments. Unwraps
   * any exception thrown from `TargetException`.
   *
   * @param  lang.XPClass $type
   * @param  var[] $arguments
   * @return var
   */
  private function invokeFixture($type, $arguments= []) {
    try {
      return Reflection::type($type)->method('fixture')->invoke(null, $arguments);
    } catch (TargetException $e) {
      throw $e->getCause();
    }
  }

  #[Test, Values([1, null, [[]]])]
  public function regular_cast($value) {
    $t= $this->type('class %T<V> {
      public static function fixture($arg) {
        return (array)$arg;
      }
    }');

    Assert::equals(
      (array)$value,
      $this->invokeFixture($t->newGenericType([Primitive::$STRING]), [$value])
    );
  }

  #[Test, Values([1, '', 'Test'])]
  public function generic_cast($value) {
    $t= $this->type('class %T<V> {
      public static function fixture($arg) {
        return (V)$arg;
      }
    }');

    Assert::equals(
      Primitive::$STRING->cast($value),
      $this->invokeFixture($t->newGenericType([Primitive::$STRING]), [$value])
    );
  }

  #[Test, Values([[[]], [[1, 2, 3]]])]
  public function generic_array_cast($value) {
    $t= $this->type('class %T<V> {
      public static function fixture($arg) {
        return (array<V>)$arg;
      }
    }');

    Assert::equals(
      (new ArrayType(Primitive::$STRING))->cast($value),
      $this->invokeFixture($t->newGenericType([Primitive::$STRING]), [$value])
    );
  }

  #[Test, Values([1, null, '', 'Test'])]
  public function generic_nullable_cast($value) {
    $t= $this->type('class %T<V> {
      public static function fixture($arg) {
        return (?V)$arg;
      }
    }');

    Assert::equals(
      Primitive::$STRING->cast($value),
      $this->invokeFixture($t->newGenericType([Primitive::$STRING]), [$value])
    );
  }

  #[Test, Values([1, '', 'Test'])]
  public function generic_union_cast($value) {
    $t= $this->type('class %T<V> {
      public static function fixture($arg) {
        return (V|int)$arg;
      }
    }');

    Assert::equals(
      (new TypeUnion([Primitive::$STRING, Primitive::$INT]))->cast($value),
      $this->invokeFixture($t->newGenericType([Primitive::$STRING]), [$value])
    );
  }

  #[Test]
  public function generic_intersection_cast() {
    $t= $this->type('use lang\Runnable; class %T<V> {
      public static function fixture($arg) {
        return (V&Runnable)$arg;
      }
    }');
    $value= new class() implements Runnable, Closeable {
      public function run() { }
      public function close() { }
    };

    $type= $t->newGenericType([new XPClass(Closeable::class)]);
    Assert::equals($value, $this->invokeFixture($type, [$value]));
  }

  #[Test]
  public function casting_used_for_coercion() {
    $t= $this->type('class %T<T> {
      private $begin, $end;

      public function __construct($range) {
        [$this->begin, $this->end]= (array<T>)$range;
      }

      public function begin(): T { return $this->begin; }

      public function end(): T { return $this->end; }
    }');

    $range= $t->newGenericType([Primitive::$INT])->newInstance(['1', '10']);
    Assert::equals([1, 10], [$range->begin(), $range->end()]);
  }
}