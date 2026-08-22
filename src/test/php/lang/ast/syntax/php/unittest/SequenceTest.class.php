<?php namespace lang\ast\syntax\php\unittest;

use lang\ast\unittest\emit\EmittingTest;
use test\{Assert, Before, Test};

class SequenceTest extends EmittingTest {
  private $sequence;

  #[Before]
  public function sequence() {
    $this->sequence= $this->type('class %T<T> {
      private $elements;

      public function __construct(T... $elements) {
        $this->elements= $elements;
      }

      public function append(T $element): self {
        $this->elements[]= $element;
        return $this;
      }

      public function map<R>(function(T): mixed $mapper): self<R> {
        $mapped= [];
        foreach ($this->elements as $element) {
          $mapped[]= $mapper($element);
        }
        return new self<R>(...$mapped);
      }

      public function toArray(): array<T> {
        return $this->elements;
      }
    }');
  }

  #[Test]
  public function append() {
    Assert::equals(['Hello', 'Test'], $this->run('class %T {
      public function run() {
        return new '.$this->sequence.'<string>()
          ->append("Hello")
          ->append("Test")
          ->toArray()
        ;
      }
    }'));
  }

  #[Test]
  public function map() {
    Assert::equals([5, 4], $this->run('class %T {
      public function run() {
        return new '.$this->sequence.'<string>()
          ->append("Hello")
          ->append("Test")
          ->map<int>(strlen(?))
          ->toArray()
        ;
      }
    }'));
  }
}