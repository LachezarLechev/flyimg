<?php

namespace Core\Handler;

use Core\Exception\SecurityException;

/**
 * Class SecurityHandler
 * @package Core\Service
 */
class SecurityHandler
{

    const ENCRYPT_METHOD = "AES-256-CBC";

    /** @var array */
    protected $defaultParams;

    /**
     * SecurityHandler constructor.
     *
     * @param array $defaultParams
     */
    public function __construct(array $defaultParams)
    {
        $this->defaultParams = $defaultParams;
    }

    /**
     * Check Restricted Domain enabled
     *
     * @param string $imageSource
     *
     * @throws SecurityException
     */
    public function checkRestrictedDomains(string $imageSource)
    {
        if ($this->defaultParams['restricted_domains'] &&
            is_array($this->defaultParams['whitelist_domains']) &&
            !in_array(parse_url($imageSource, PHP_URL_HOST), $this->defaultParams['whitelist_domains'])
        ) {
            throw  new SecurityException(
                'Restricted domains enabled, the domain your fetching from is not allowed: '.
                parse_url($imageSource, PHP_URL_HOST)
            );
        }
    }

    /**
     * @param string $options
     * @param string $imageSrc
     *
     * @return array
     * @throws SecurityException
     */
    public function checkSecurityHash(string $options, string $imageSrc): array
    {
        if (empty($this->defaultParams['security_key'])) {
            return [$options, $imageSrc];
        }

        if (empty($this->defaultParams['security_iv'])) {
            throw  new SecurityException(
                'Security iv is not set in parameters.yml (security_iv)'
            );
        }

        $decryptedHash = $this->decrypt($options);

        if (empty($decryptedHash)) {
            throw  new SecurityException(
                "Security Key enabled: Requested URL doesn't match with the hashed Security key !"
            );
        };

        list($explodedOptions, $explodedImageSrc) = explode('/', $decryptedHash, 2);

        if (empty($explodedImageSrc) || empty($explodedOptions)) {
            throw  new SecurityException(
                "Something went wrong when decrypting the hashed URL: ".
                $options
            );
        }

        return [$explodedOptions, $explodedImageSrc];
    }

    /**
     * @param $string
     *
     * @return string
     */
    public function encrypt(string $string): string
    {
        list($secretKey, $secretIv) = $this->createHash();
        $output = base64_encode(openssl_encrypt($string, self::ENCRYPT_METHOD, $secretKey, 0, $secretIv));

        return $output;
    }

    /**
     * @param $string
     *
     * @return string
     */
    public function decrypt(string $string): string
    {
        list($secretKey, $secretIv) = $this->createHash();
        $output = openssl_decrypt(base64_decode($string), self::ENCRYPT_METHOD, $secretKey, 0, $secretIv);

        return $output;
    }

    /**
     * @return array
     * @throws SecurityException
     */
    protected function createHash(): array
    {
        $secretKey = $this->defaultParams['security_key'];
        $secretIv = $this->defaultParams['security_iv'];

        if (empty($secretKey)) {
            throw  new SecurityException(
                "security_key in empty im parameters.yml!"
            );
        }
        // hash
        $secretKey = hash('sha256', $secretKey);

        //initialization vector(IV) - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $secretIv = substr(hash('sha256', $secretIv), 0, 16);

        return [$secretKey, $secretIv];
    }
}
