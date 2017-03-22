<?php

namespace Seqr\Constraints;

/**
 * Class Items
 * @package Seqr
 */
class Items extends Constraint
{

    /**
     * @return mixed
     */
    public function getItems() {

        foreach($this->data as &$item) {
            $item = new Item($item);
            $item = $item->toArray();
        }

        return $this->data;
    }

    public function getTotal() {

        return array_sum(array_map(function($arr) {
            return $arr['itemTotalAmount']['value'];
        }, $this->getItems()));

    }
}
