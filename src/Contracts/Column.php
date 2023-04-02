<?php

namespace AliAlizade\LaravelMaskedDumper\Contracts;

interface Column
{
    public function modifyValue($value);
}
