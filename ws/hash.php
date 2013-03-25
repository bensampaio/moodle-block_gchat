<?php
interface HashCodeProvider {
    public function getHashCode();
}

class HashMap implements ArrayAccess {

    private $keys = array();

    private $values = array();

    public function __construct($values = array()) {
        foreach($values as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function offsetExists($offset) {
        $hash = $this->getHashCode($offset);

        return isset($this->values[$hash]);
    }

    public function offsetGet($offset) {
        $hash = $this->getHashCode($offset);

        return $this->values[$hash];
    }

    public function offsetSet($offset, $value) {
        $hash = $this->getHashCode($offset);

        $this->keys[$hash] = $offset;
        $this->values[$hash] = $value;
    }

    public function offsetUnset($offset) {
        $hash = $this->getHashCode($offset);

        unset($this->keys[$hash]);
        unset($this->values[$hash]);
    }

    public function keys() {
        return array_values($this->keys);
    }

    public function values() {
        return array_values($this->values);
    }

    private function getHashCode($object) {
        if(is_object($object)) {
            if($object instanceof HashCodeProvider) {
                return $object->getHashCode();
            }
            else {
                return spl_object_hash($object);
            }
        }
        else {
            return $object;
        }
    }

}
