<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\{Primitive, Nullable, ArrayType, MapType, TypeUnion, FunctionType, IllegalArgumentException};
use unittest\{Assert, Test};

class GenericsTest extends EmittingTest {

  #[Test]
  public function is_generic_definition() {
    $t= $this->type('class <T><E> { }');
    Assert::true($t->isGenericDefinition());
  }

  #[Test]
  public function generic_component() {
    $t= $this->type('class <T><E> { }');
    Assert::equals(['E'], $t->genericComponents());
  }

  #[Test]
  public function generic_components() {
    $t= $this->type('class <T><K, V> { }');
    Assert::equals(['K', 'V'], $t->genericComponents());
  }

  #[Test]
  public function new_generic_type() {
    $t= $this->type('class <T><E> { }')->newGenericType([Primitive::$STRING]);
    Assert::true($t->isGeneric());
  }

  #[Test]
  public function generic_arguments() {
    $t= $this->type('class <T><E> { }')->newGenericType([Primitive::$STRING]);
    Assert::equals([Primitive::$STRING], $t->genericArguments());
  }

  #[Test]
  public function new_creates_generic_types() {
    $r= $this->run('class <T><E> {
      public function run() {
        return new self<string>();
      }
    }');
    Assert::true(typeof($r)->isGeneric());
  }

  #[Test]
  public function generic_parameter_type() {
    $t= $this->type('class <T><E> {
      public function push(E $element) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals($c, $t->newGenericType([$c])->getMethod('push')->getParameter(0)->getType());
  }

  #[Test]
  public function generic_type_union() {
    $t= $this->type('class <T><E> {
      public function push(int|E $element) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new TypeUnion([Primitive::$INT, $c]),
      $t->newGenericType([$c])->getMethod('push')->getParameter(0)->getType()
    );
  }

  #[Test]
  public function generic_function_type() {
    $t= $this->type('class <T><E> {
      public function comparing(function(E, E): int $comparator) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new FunctionType([$c, $c], Primitive::$INT),
      $t->newGenericType([$c])->getMethod('comparing')->getParameter(0)->getType()
    );
  }

  #[Test]
  public function nullable_generic_function_type() {
    $t= $this->type('class <T><E> {
      public function comparing(?function(E, E): int $comparator) { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(
      new Nullable(new FunctionType([$c, $c], Primitive::$INT)),
      $t->newGenericType([$c])->getMethod('comparing')->getParameter(0)->getType()
    );
  }

  #[Test]
  public function generic_return_type() {
    $t= $this->type('class <T><E> {
      public function pop(): ?E { }
    }');

    $c= Primitive::$STRING;
    Assert::equals(new Nullable($c), $t->newGenericType([$c])->getMethod('pop')->getReturnType());
  }

  #[Test]
  public function generic_array_type() {
    $t= $this->type('class <T><E> {
      public array<E> $elements;
    }');

    $c= Primitive::$STRING;
    Assert::equals(new ArrayType($c), $t->newGenericType([$c])->getField('elements')->getType());
  }

  #[Test]
  public function generic_map_type() {
    $t= $this->type('class <T><K, V> {
      public array<K, V> $pairs;
    }');

    $k= Primitive::$STRING;
    $v= Primitive::$INT;
    Assert::equals(new MapType($v), $t->newGenericType([$k, $v])->getField('pairs')->getType());
  }

  #[Test]
  public function string_queue() {
    $r= $this->run('class <T><E> {
      private array<E> $elements= [];

      public function __construct(E... $elements) {
        $this->elements= $elements;
      }

      public function push(E $element): self {
        $this->elements[]= $element;
        return $this;
      }

      public function pop(): ?E {
        return array_pop($this->elements);
      }

      public function remaining(): array<E> {
        return $this->elements;
      }

      public function run() {
        return new self<string>("A", "B")->push("C");
      }
    }');

    Assert::equals('C', $r->pop());
    Assert::equals(['A', 'B'], $r->remaining());
  }

  #[Test, Expect(class: IllegalArgumentException::class, withMessage: '/Argument 1 .+ must be of string, int given/')]
  public function incorrect_parameter_type() {
    $this->run('class <T><E> {
      public function push(E $element) { /* Never run */ }

      public function run() {
        return new self<string>()->push(123);
      }
    }');
  }

  #[Test, Expect(class: IllegalArgumentException::class, withMessage: '/Vararg 1 .+ must be of string.*, var.* given/')]
  public function incorrect_variadic_parameter_type() {
    $this->run('class <T><E> {
      public function push(E... $elements) { /* Never run */ }

      public function run() {
        return new self<string>()->push(123);
      }
    }');
  }

  #[Test, Expect(class: IllegalArgumentException::class, withMessage: '/Argument 1 .+ must be of .+, .+ given/')]
  public function incorrect_function_type() {
    $this->run('class <T><E> {
      public function comparing(function(E, E): int $comparator) { /* Never run */ }

      public function run() {
        return new self<string>()->comparing(fn(int $a, int $b): int => $a <=> $b);
      }
    }');
  }
}