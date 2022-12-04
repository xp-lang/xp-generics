<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\reflect\TargetInvocationException;
use lang\{ArrayType, Primitive};
use test\{Assert, Expect, Test, Values};

class CastingTest extends EmittingTest {

  /**
   * Invokes static fixture method with the given arguments. Unwraps
   * any exception thrown from `TargetInvocationException`.
   *
   * @param  lang.XPClass $type
   * @param  var[] $arguments
   * @return var
   */
  private function invokeFixture($type, $arguments= []) {
    try {
      return $type->getMethod('fixture')->invoke(null, $arguments);
    } catch (TargetInvocationException $e) {
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
}