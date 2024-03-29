<?php

namespace Dct24\EmailLabs\Transport;

use GuzzleHttp\Post\PostFile;
use Swift_Mime_SimpleMessage;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Transport\Transport;

class EmailLabsTransport extends Transport
{
    /**
     * Guzzle client instance.
     *
     * @var GuzzleHttp\ClientInterface
     **/
    protected $client;

    /**
     * The EmailLabs API Secret Key
     *
     * @var string
     **/
    protected $secret;

    /**
     * The EmailLabs smtp account name
     *
     * @var string
     **/
    protected $smtpAccount;

    /**
     * The EmailLabs API App Key
     *
     * @var string
     **/
    protected $app;

    public function __construct(ClientInterface $client, array $config)
    {
        $this->client = $client;
        $this->secret = $config['secret'];
        $this->app = $config['app'];
        $this->smtpAccount = $config['smtp'];
    }

    /**
     * undocumented function
     *
     * @return void
     * @author Sebastian
     **/
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $data = $this->prepareData($message);

        if (version_compare(ClientInterface::VERSION, '6') === 1) {
            $options = ['form_params' => $data];
        } else {
            $options = ['body' => $data];
        }

        $options['auth'] = [$this->app, $this->secret];

        try {
            $response = json_decode($this->client
                ->post('https://api.emaillabs.net.pl/api/sendmail', $options)
                ->getBody(), true);

            if ($response['status'] === 'success') {
                Log::info('Wiadomość wysłana. '.$response['message']. ' '
                    .$this->formatResponseData($response));
            } else {
                Log::warning('Wiadomość nie wysłana. '.$response['message'].' '
                    .$this->formatResponseData($response));
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
    }

    /**
     * Prepare message data array from Swift message
     *
     * @return array
     * @author
     **/
    protected function prepareData(Swift_Mime_SimpleMessage $message)
    {
        $data =  [
            'smtp_account' => $this->smtpAccount,
            'to' => $this->getAddresses($message->getTo()),
            'cc' => $this->getFirstAddressFromAddresses($message->getCc()),
            'cc_name' => $this->getFirstNameFromAddresses($message->getCc()),
            'bcc' => $this->getFirstAddressFromAddresses($message->getBcc()),
            'bcc_name' => $this->getFirstNameFromAddresses($message->getBcc()),
            'from' => $this->getFirstAddressFromAddresses($message->getFrom()),
            'from_name' => $this->getFirstNameFromAddresses($message->getFrom()),
            'reply_to' => $this->getFirstNameFromAddresses($message->getReplyTo()),
            'html' => $message->getBody(),
            'subject' => substr($message->getSubject(), 0, 128)
        ];

        if ($attachments = $message->getChildren()) {
            $data['files'] = array_map(function ($attachment) {
                if (is_object($attachment) and $attachment instanceof \Swift_Mime_Attachment) {
                    return [
                        'mime' => $attachment->getContentType(),
                        'name' => $attachment->getFileName(),
                        'content' => base64_encode($attachment->getBody()),
                    ];
                }
            }, $attachments);
        }

        return $data;
    }

    /**
     * Convert response data array to string
     *
     * @return string
     * @author
     **/
    protected function formatResponseData($response)
    {
        $result = '';
        foreach ($response['data'][0] as $key => $value) {
            $result.=$key.':'.$value.';';
        }

        return $result;
    }

    /**
     * Get all the addresses this message should be sent to.
     *
     * @param  array|null $addresses
     * @return array
     * @author
     **/
    protected function getAddresses($addresses)
    {
        $to = [];

        if ($addresses) {
            $to = array_merge($to, array_keys($addresses));
        }

        return $to;
    }

    /**
     *  Recive if exists first recipient address from addresses array
     *
     * @return string
     * @author
     **/
    protected function getFirstAddressFromAddresses($addresses)
    {
        if ($addresses) {
            return array_keys($addresses)[0];
        }

        return '';
    }

    /**
     * Recive if exists first recipient name from addresses array
     *
     * @return string
     * @author
     **/
    protected function getFirstNameFromAddresses($addresses)
    {
        if ($addresses) {
            return substr(array_values($addresses)[0], 0, 128);
        }

        return '';
    }

    /**
     * Get API key
     *
     * @return string
     * @author Sebastian
     **/
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set API key
     *
     * @return string $key
     * @author Sebastian
     **/
    public function setKey($key)
    {
        return $this->key = $key;
    }
}
