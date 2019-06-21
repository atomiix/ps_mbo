<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\Mbo\Adapter;

use Closure;
use PrestaShop\CircuitBreaker\FactorySettings;
use PrestaShop\CircuitBreaker\SimpleCircuitBreakerFactory;
use PrestaShop\Module\Mbo\ExternalContentProviderInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExternalContentProvider implements ExternalContentProviderInterface
{
    const ALLOWED_FAILURES = 2;

    const TIMEOUT_SECONDS = 0.6;

    const THRESHOLD_SECONDS = 3600; // Retry in 1 hour

    /**
     * @var SimpleCircuitBreakerFactory
     */
    private $circuitBreakerFactory;

    /**
     * @var OptionsResolver
     */
    private $optionsResolver;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->circuitBreakerFactory = new SimpleCircuitBreakerFactory();
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions();
    }

    /**
     * {@inheritdoc}
     *
     * @throws MissingOptionsException
     * @throws ServiceUnavailableHttpException
     */
    public function call(array $options)
    {
        $settings = $this->optionsResolver->resolve($options);

        $apiSettings = new FactorySettings(
            $settings['failures'],
            $settings['timeout'],
            $settings['threshold']
        );

        $circuitBreaker = $this->circuitBreakerFactory->create($apiSettings);

        return $circuitBreaker->call(
            $settings['url'],
            $settings['client_options'],
            $this->circuitBreakerFallback()
        );
    }

    private function configureOptions()
    {
        $this->optionsResolver->setDefaults([
            'failures' => self::ALLOWED_FAILURES,
            'timeout' => self::TIMEOUT_SECONDS,
            'threshold' => self::THRESHOLD_SECONDS,
            'url' => '',
            'client_options' => [],
        ]);
        $this->optionsResolver->setRequired('url');
        $this->optionsResolver->setAllowedTypes('url', 'string');
        $this->optionsResolver->setAllowedTypes('failures', 'numeric');
        $this->optionsResolver->setAllowedTypes('timeout', 'numeric');
        $this->optionsResolver->setAllowedTypes('threshold', 'numeric');
        $this->optionsResolver->setAllowedTypes('client_options', 'array');
    }

    /**
     * Called by CircuitBreaker if the service is unavailable
     *
     * @return Closure
     */
    private function circuitBreakerFallback()
    {
        return function () {
            throw new ServiceUnavailableHttpException(self::THRESHOLD_SECONDS);
        };
    }
}
