<?php

namespace FM\Payment\AuthorizenetBundle\Form;

use Symfony\Component\Form\AbstractType;

class AuthorizenetType extends AbstractType
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}
