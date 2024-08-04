<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use lang\{ArrayType, MapType, Primitive, Reflection, XPClass};
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
    $t= $this->type('class %T<E> implements '.$i->literal().'<E> { }');

    $c= Primitive::$STRING;
    Assert::equals([$c], Reflection::type($t->newGenericType([$c]))->interfaces()[0]->class()->genericArguments());
  }

  #[Test]
  public function extends_generic_base_class() {
    $i= $this->type('abstract class %T<E> { }');
    $t= $this->type('class %T<E> extends '.$i->literal().'<E> { }');

    $c= Primitive::$STRING;
    Assert::equals([$c], $t->newGenericType([$c])->getParentclass()->genericArguments());
  }

  #[Test]
  public function extends_generic_interface() {
    $i= $this->type('interface %T<E> { }');
    $t= $this->type('interface %T<E> extends '.$i->literal().'<E> { }');

    $c= Primitive::$STRING;
    Assert::equals([$c], Reflection::type($t->newGenericType([$c]))->interfaces()[0]->class()->genericArguments());
  }

  #[Test]
  public function extends_generic_parent_with_type_argument() {
    $i= $this->type('abstract class %T<E> {
      public function defaultValue(): E { return $E->default; }
    }');
    $t= $this->type('class %T extends '.$i->literal().'<string> { }');

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
  public function new_component() {
    $t= $this->type('class %T<E> {
      public static function fixture($arg) {
        return new E($arg);
      }
    }');
    Assert::equals(6100, Reflection::type($t->newGenericType([Primitive::$INT]))
      ->method('fixture')
      ->invoke(null, ['6100'])
    );
  }

  #[Test]
  public function new_class_of_component() {
    $t= $this->type('class %T<E> {
      public static function fixture() {
        return new %T<E>();
      }
    }');
    $type= $t->newGenericType([Primitive::$INT]);

    Assert::instance($type, Reflection::type($type)->method('fixture')->invoke(null, []));
  }

  #[Test]
  public function instanceof_component() {
    $t= $this->type('class %T<E> {
      public static function fixture($arg) {
        return $arg instanceof E;
      }
    }');
    Assert::equals(true, Reflection::type($t->newGenericType([new XPClass(self::class)]))
      ->method('fixture')
      ->invoke(null, [$this])
    );
  }

  #[Test]
  public function instanceof_self_with_component() {
    $t= $this->type('class %T<E> {
      public static function fixture() {
        return new self<E>() instanceof self<E>;
      }
    }');
    Assert::equals(true, Reflection::type($t->newGenericType([new XPClass(self::class)]))
      ->method('fixture')
      ->invoke(null, [])
    );
  }

  #[Test]
  public function generic_class() {
    $t= $this->type('class %T<E> {
      public static function fixture() {
        return %T<string>::class;
      }
    }');
    $invoke= $t->newGenericType([new XPClass(self::class)]);
    $return= $t->newGenericType([Primitive::$STRING]);

    Assert::equals($return->literal(), Reflection::type($invoke)->method('fixture')->invoke(null, []));
  }

  #[Test]
  public function generic_self_class() {
    $t= $this->type('class %T<E> {
      public static function fixture() {
        return self<string>::class;
      }
    }');
    $invoke= $t->newGenericType([new XPClass(self::class)]);
    $return= $t->newGenericType([Primitive::$STRING]);

    Assert::equals($return->literal(), Reflection::type($invoke)->method('fixture')->invoke(null, []));
  }

  #[Test]
  public function generic_component_class() {
    $t= $this->type('class %T<E> {
      public static function fixture() {
        return %T<E>::class;
      }
    }');
    $invoke= $t->newGenericType([new XPClass(self::class)]);

    Assert::equals($invoke->literal(), Reflection::type($invoke)->method('fixture')->invoke(null, []));
  }

  #[Test]
  public function component_class() {
    $t= $this->type('class %T<E> {
      public static function fixture() {
        return E::class;
      }
    }');
    Assert::equals(self::class, Reflection::type($t->newGenericType([new XPClass(self::class)]))
      ->method('fixture')
      ->invoke(null, [])
    );
  }

  #[Test]
  public function string_queue() {
    $r= $this->run('namespace test; class %T<E> {
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