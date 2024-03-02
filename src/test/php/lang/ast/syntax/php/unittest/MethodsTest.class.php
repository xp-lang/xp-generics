<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\{
  ArrayType,
  FunctionType,
  IllegalArgumentException,
  MapType,
  Nullable,
  Primitive,
  Reflection,
  TypeUnion,
  Wildcard,
  WildcardType
};
use test\{Assert, Expect, Test};

class MethodsTest extends EmittingTest {

  /**
   * Returns type of the given method's first parameter.
   *
   * @param  lang.Type $type
   * @param  string $method
   * @return lang.Type
   */
  private function parameterType($type, $method) {
    return Reflection::type($type)->method($method)->parameter(0)->constraint()->type();
  }

  /**
   * Returns return type of the given method.
   *
   * @param  lang.Type $type
   * @param  string $method
   * @return lang.Type
   */
  private function returnType($type, $method) {
    return Reflection::type($type)->method($method)->returns()->type();
  }

  #[Test]
  public function generic_parameter_type() {
    $t= $this->type('class %T<E> {
      public function push(E $element) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals($c, $this->parameterType($t->newGenericType([$c]), 'push'));
  }

  #[Test]
  public function generic_type_union() {
    $t= $this->type('class %T<E> {
      public function push(int|E $element) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new TypeUnion([Primitive::$INT, $c]),
      $this->parameterType($t->newGenericType([$c]), 'push')
    );
  }

  #[Test]
  public function generic_function_type() {
    $t= $this->type('class %T<E> {
      public function comparing(function(E, E): int $comparator) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new FunctionType([$c, $c], Primitive::$INT),
      $this->parameterType($t->newGenericType([$c]), 'comparing')
    );
  }

  #[Test]
  public function nullable_generic_function_type() {
    $t= $this->type('class %T<E> {
      public function comparing(?function(E, E): int $comparator) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new Nullable(new FunctionType([$c, $c], Primitive::$INT)),
      $this->parameterType($t->newGenericType([$c]), 'comparing')
    );
  }

  #[Test]
  public function generic_return_type() {
    $t= $this->type('class %T<E> {
      public function pop(): ?E { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(new Nullable($c), $this->returnType($t->newGenericType([$c]), 'pop'));
  }

  #[Test]
  public function generic_type() {
    $l= $this->type('class %T<E> { }');
    $t= $this->type('class %T<E> {
      public function copy(): '.$l->literal().'<E> { /* Not implemented */ }
    }');

    $c= Primitive::$STRING;
    Assert::equals($l->newGenericType([$c]), $this->returnType($t->newGenericType([$c]), 'copy'));
  }

  #[Test, Expect(class: IllegalArgumentException::class, message: '/Argument 1 .+ must be of string, int given/')]
  public function incorrect_parameter_type() {
    $this->run('class %T<E> {
      public function push(E $element) { /* Never run */ }

      public function run() {
        return new self<string>()->push(123);
      }
    }');
  }

  #[Test, Expect(class: IllegalArgumentException::class, message: '/Vararg 1 .+ must be of string.*, var.* given/')]
  public function incorrect_variadic_parameter_type() {
    $this->run('class %T<E> {
      public function push(E... $elements) { /* Never run */ }

      public function run() {
        return new self<string>()->push(123);
      }
    }');
  }

  #[Test, Expect(class: IllegalArgumentException::class, message: '/Argument 1 .+ must be of .+, .+ given/')]
  public function incorrect_function_type() {
    $this->run('class %T<E> {
      public function comparing(function(E, E): int $comparator) { /* Never run */ }

      public function run() {
        return new self<string>()->comparing(fn(int $a, int $b): int => $a <=> $b);
      }
    }');
  }

  #[Test]
  public function generic_type_inside_non_generic_class() {
    $q= $this->type('class %T<E> { }');
    $t= $this->type('class %T {
      public function all('.$q->getName().'<?> $queue): iterable { /* Not implemented */ }
    }');

    Assert::equals(
      new WildcardType($q, [Wildcard::$ANY]),
      $t->getMethod('all')->getParameter(0)->getType()
    );
  }
}