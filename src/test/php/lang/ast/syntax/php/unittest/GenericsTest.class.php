<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\{ArrayType, MapType, Primitive, Reflection};
use test\{Assert, Test, Values};

class GenericsTest extends EmittingTest {

  #[Test, Values(['class %T<E> { }', 'interface %T<E> { }'])]
  public function is_generic_definition($declaration) {
    $t= $this->type($declaration);
    Assert::true($t->isGenericDefinition());
  }

  #[Test]
  public function generic_component() {
    $t= $this->type('class %T<E> { }');
    Assert::equals(['E'], $t->genericComponents());
  }

  #[Test]
  public function generic_components() {
    $t= $this->type('class %T<K, V> { }');
    Assert::equals(['K', 'V'], $t->genericComponents());
  }

  #[Test]
  public function new_generic_type() {
    $t= $this->type('class %T<E> { }')->newGenericType([Primitive::$STRING]);
    Assert::true($t->isGeneric());
  }

  #[Test]
  public function generic_arguments() {
    $t= $this->type('class %T<E> { }')->newGenericType([Primitive::$STRING]);
    Assert::equals([Primitive::$STRING], $t->genericArguments());
  }

  #[Test]
  public function implements_generic_interface() {
    $i= $this->type('interface %T<E> { }');
    $t= $this->type('class %T<E> implements '.$i->getName().'<E> { }');

    $c= Primitive::$STRING;
    Assert::equals([$c], $t->newGenericType([$c])->getInterfaces()[0]->genericArguments());
  }

  #[Test]
  public function extends_generic_base_class() {
    $i= $this->type('abstract class %T<E> { }');
    $t= $this->type('class %T<E> extends '.$i->getName().'<E> { }');

    $c= Primitive::$STRING;
    Assert::equals([$c], $t->newGenericType([$c])->getParentclass()->genericArguments());
  }

  #[Test]
  public function extends_generic_interface() {
    $i= $this->type('interface %T<E> { }');
    $t= $this->type('interface %T<E> extends '.$i->getName().'<E> { }');

    $c= Primitive::$STRING;
    Assert::equals([$c], $t->newGenericType([$c])->getInterfaces()[0]->genericArguments());
  }

  #[Test]
  public function extends_generic_parent_with_type_argument() {
    $i= $this->type('abstract class %T<E> {
      public function defaultValue(): E { return $E->default; }
    }');
    $t= $this->type('class %T extends '.$i->getName().'<string> { }');

    $c= Primitive::$STRING;
    Assert::equals([$c], $t->getParentclass()->genericArguments());
    Assert::equals('', $t->newInstance()->defaultValue());
  }

  #[Test]
  public function new_creates_generic_types() {
    $r= $this->run('class %T<E> {
      public function run() {
        return new self<string>();
      }
    }');
    Assert::true(typeof($r)->isGeneric());
  }

  #[Test]
  public function string_queue() {
    $r= $this->run('class %T<E> {
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
}