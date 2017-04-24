<?php

namespace FM\Payment\AuthorizenetBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class CheckoutType extends AuthorizenetType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('token', 'hidden', array(
            'required' => false
        ));
    }
}
