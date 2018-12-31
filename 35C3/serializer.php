<?php

class B {
  function __destruct() {
    echo "You get a flag";
  }
}

echo(serialize(new B));