<?php namespace lang\ast\syntax\php;

use lang\ast\Node;
use lang\ast\nodes\Member;

class GenericMethod extends Node implements Member {
  private $delegate;
  public $kind= 'genericmethod';

  /** @param lang.ast.nodes.Member $delegate */
  public function __construct($delegate) {
    $this->delegate= $delegate;
  }

  /** @return string */
  public function lookup() { return $this->delegate->lookup(); }
}