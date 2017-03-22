<?php

namespace Ansta/Sequr;

use Seqr\Constraints\Items;
use Seqr\Constraints\Order;
use Seqr\Exceptions\SeqrException;

/**
 * Class Sequr
 * @package Ansta
 */
class Sequr extends \SoapClient
{
    /**
     * @var string
     */
    private static $route = 'https://extdev.seqr.com/extclientproxy/service/v2?wsdl';//todo do a testing config to change the route

    /**
     * @var array
     */
    private static $configs = [];

    /**
     * @var array
     */
    private $requiredConfigs = [
        'terminalId',
        'password',
        'currency',
    ];

    /**
     * Sequr constructor.
     * @param array $configs
     */
    public function __construct(Array $configs = [])
    {
        $this->setConfigs($configs);
        parent::__construct(self::$route);
    }

    /**
     * @param array $configs
     */
    private function setConfigs($configs)
    {
        foreach(self::$requiredConfigs as $required) {
            if (!in_array($required, $configs)) throw new SeqrRequiredConfigException($required . ' is a required config');
        }

        self::$configs = $configs;
    }

    /**
     * @param Items $items
     * @param Order $order
     * @param bool $acknowledgement
     * @param null $backURL
     * @param null $notificationUrl
     * @return mixed
     * @throws SeqrException
     */
    public function sendInvoice(Items $items, Order $order, $acknowledgement = false, $backURL = null, $notificationUrl = null)
    {
        $invoice = [
            'acknowledgementMode' => $acknowledgement ? 'ACKNOWLEDGE' : 'NO_ACKNOWLEDGE',
            'invoiceRows' => $items->items,
            'totalAmount' => [
                'currency' => self::$configs['currency'],
                'value' => $items->total,
            ],
            'title' => $order->id,
        ];

        if ($backURL) $invoice['backURL'] = $backURL;
        if ($notificationUrl) $invoice['notificationUrl'] = $notificationUrl;

        try {

            $result = parent::sendInvoice([
                'context' => $this->getContextArray(),
                'invoice' => $invoice,
            ]);

        } catch (\Exception $e) {
            throw new SeqrException($e->getMessage());
        }

        return json_decode($result->return);

    }

    /**
     * @param $invoiceReference
     * @param int $invoiceVersion
     * @return mixed
     * @throws SeqrException
     */
    public function getPaymentStatus($invoiceReference, $invoiceVersion = 0)
    {
        try {
            $result = parent::getPaymentStatus([
                'context' => $this->getContextArray(),
                'invoiceReference' => $invoiceReference,
                'invoiceVersion' => $invoiceVersion,
            ]);
        } catch (\Exception $e) {
            throw new SeqrException($e->getMessage());
        }

        return json_decode($result);
    }

    /**
     * @return array
     */
    protected function getContextArray()
    {
        return [
            'initiatorPrincipalId' => [
                'id' => self::$configs['terminalId'],
                'type' => 'TERMINALID',
            ],
            'password' => self::$configs['password'],
            'clientRequestTimeout' => 0,
        ];
    }

}

