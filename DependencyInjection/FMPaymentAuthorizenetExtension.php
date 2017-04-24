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

        $this->addCheckoutPluginInstance($container, $config);

        foreach($config['instances'] AS $instance => $options) {
            $this->addCheckoutPluginInstance($container, $options);
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
        $authorizenetMethod = 'authorizenet_' . $method;

        $definition = new Definition();
        if($container->hasParameter(sprintf('fm_payment_authorizenet.form.%s_type.class', $method))) {
            $definition->setClass(sprintf('%%fm_payment_authorizenet.form.%s_type.class%%', $method));
        } else {
            $definition->setClass('%fm_payment_authorizenet.form.authorizenet_type.class%');
        }
        $definition->addArgument($authorizenetMethod);

        $definition->addTag('payment.method_form_type');
        $definition->addTag('form.type', array(
            'alias' => $authorizenetMethod
        ));

        $container->setDefinition(
            sprintf('fm_payment_authorizenet.form.%s_type', $method),
            $definition
        );
    }

    private function addCheckoutPluginInstance(ContainerBuilder $container, $options)
    {
        $gatewayDefinition = new Definition('%fm_payment_authorizenet.gateway.class%', [null, new Reference('request', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)]);
        $container->setDefinition('fm_payment_authorizenet.gateway.aim', $gatewayDefinition);

        $pluginDefinition = new Definition("%fm_payment_authorizenet.plugin.checkout.class%", [ new Reference('fm_payment_authorizenet.gateway.aim') ]);
        $pluginDefinition->addMethodCall('setLogger', [ new Reference('monolog.logger.fm_payment_authorizenet') ]);
        $pluginDefinition->addMethodCall('setProcessesType', [ $options['processes_type'] ]);
        $pluginDefinition->addTag('payment.plugin');
        $container->setDefinition('fm_payment_authorizenet.plugin.checkout', $pluginDefinition);
    }
}
