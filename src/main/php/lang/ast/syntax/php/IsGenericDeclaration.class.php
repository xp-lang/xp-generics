<?php namespace lang\ast\syntax\php;

use lang\ast\Type;

class IsGenericDeclaration extends Type {
  private $type;

  public function __construct($type) {
    $this->type= $type;
  }

  /** @return string */
  public function name() { return $this->type->base->name(); }

  /** @return string */
  public function literal() { return $this->type->base->literal(); }

  /** @return parent[] */
  public function components() { return $this->type->components; }
}