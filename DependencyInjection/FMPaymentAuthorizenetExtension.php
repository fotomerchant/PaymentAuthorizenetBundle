<?php

namespace FM\Payment\AuthorizenetBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class FMPaymentAuthorizenetExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        // If an API Key is provided, we will setup the 'default' stripe checkout plugin for 'stripe_checkout'
        // payment types. This ensure no BC breaks for this bundle.
        if (isset($config['api_key'])) {
            $this->addCheckoutPluginInstance($container, 'default', $config);

            // Add aliases for BC
            $container->setAlias('fm_payment_authorizenet.gateway', 'fm_payment_authorizenet.gateway.default');
            $container->setAlias('fm_payment_authorizenet.plugin.checkout', 'fm_payment_authorizenet.plugin.checkout.default');
        }

        foreach($config['instances'] AS $instance => $options) {
            $this->addCheckoutPluginInstance($container, $instance, $options);
        }

        foreach($config['methods'] AS $method) {
            $this->addFormType($container, $method);
        }

        /**
         * When logging is disabled, remove logger and setLogger calls
         */
        if(false === $config['logger']) {
            $container->getDefinition('fm_payment_authorizenet.plugin.credit_card')->removeMethodCall('setLogger');
            $container->removeDefinition('monolog.logger.fm_payment_authorizenet');
        }
    }

    protected function addFormType(ContainerBuilder $container, $method)
    {
        $stripeMethod = 'stripe_' . $method;

        $definition = new Definition();
        if($container->hasParameter(sprintf('fm_payment_authorizenet.form.%s_type.class', $method))) {
            $definition->setClass(sprintf('%%fm_payment_authorizenet.form.%s_type.class%%', $method));
        } else {
            $definition->setClass('%fm_payment_authorizenet.form.authorizenet_type.class%');
        }
        $definition->addArgument($stripeMethod);

        $definition->addTag('payment.method_form_type');
        $definition->addTag('form.type', array(
            'alias' => $stripeMethod
        ));

        $container->setDefinition(
            sprintf('fm_payment_authorizenet.form.%s_type', $method),
            $definition
        );
    }

    private function addCheckoutPluginInstance($container, $instance, $options)
    {
        $gatewayDefinition = new Definition('%fm_payment_authorizenet.gateway.class%', [null, new Reference('request', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)]);
        $gatewayDefinition->addMethodCall('setApiKey', [ $options['api_key'] ]);
        $container->setDefinition('fm_payment_authorizenet.gateway.'.$instance, $gatewayDefinition);

        $pluginDefinition = new Definition("%fm_payment_authorizenet.plugin.checkout.class%", [ new Reference('fm_payment_authorizenet.gateway.'.$instance) ]);
        $pluginDefinition->addMethodCall('setLogger', [ new Reference('monolog.logger.fm_payment_authorizenet') ]);
        $pluginDefinition->addMethodCall('setProcessesType', [ $options['processes_type'] ]);
        $pluginDefinition->addTag('payment.plugin');
        $container->setDefinition('fm_payment_authorizenet.plugin.checkout.'.$instance, $pluginDefinition);
    }
}
