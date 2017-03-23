<?php

namespace Ansta\Seqr;

use Ansta\Seqr\Constraints\Items;
use Ansta\Seqr\Constraints\Order;
use Ansta\Seqr\Exceptions\SeqrException;
use Ansta\Seqr\Exceptions\SeqrRequiredConfigException;
use BaconQrCode\Renderer\Image\Png as QR;
use BaconQrCode\Writer;

/**
 * Class Sequr
 * @package Ansta
 */
class Seqr extends \SoapClient
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
    private static $requiredConfigs = [
        'terminalId',
        'password',
        'currency',
        'qrImagePath',
    ];

    /**
     * Sequr constructor.
     * @param array $configs
     */
    public function __construct(Array $configs = [])
    {
        $this->setConfigs($configs);
        parent::__construct(self::$route, [
            'exceptions' => true,
            'trace' => true,
        ]);
    }

    /**
     * @param array $configs
     */
    private function setConfigs($configs)
    {
        foreach(self::$requiredConfigs as $required) {
            if (!in_array($required, array_keys($configs))) throw new SeqrRequiredConfigException($required . ' is a required config');
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
    public function sendInvoice(Items $items, Order $order, $acknowledgement = false, $qrcode = true, $backURL = null, $notificationUrl = null)
    {
        $invoice = [
            'acknowledgmentMode' => $acknowledgement ? 'ACKNOWLEDGMENT' : 'NO_ACKNOWLEDGMENT',
            'title' => 'Testing',
            'totalAmount' => [
                'currency' => self::$configs['currency'],
                'value' => $order->amount,
            ],
            'invoiceRows' => $items->items,
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

        if ($qrcode) {
            $renderer = new QR();

            $renderer->setHeight(300);
            $renderer->setWidth(300);

            if (!file_exists(self::$configs['qrImagePath'].DIRECTORY_SEPARATOR)) throw new SeqrException('Path for ' . self::$configs['qrImagePath'].DIRECTORY_SEPARATOR . ' was not found');

            $name = $result->return->invoiceReference.'.png';

            $writer = new Writer($renderer);
            $writer->writeFile($result->return->invoiceQRCode, $name);

            rename(PUBLIC_ROOT.$name, self::$configs['qrImagePath'].DIRECTORY_SEPARATOR.$name);

            $parts = explode('/public_html/', self::$configs['qrImagePath']);

            return array_merge((array)$result->return, ['QRImage' => $parts[1].DIRECTORY_SEPARATOR.$name]);
        }

        else return (array) $result->return;

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

        return (array) $result->return;
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

