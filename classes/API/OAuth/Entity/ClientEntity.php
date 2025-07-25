<?php

namespace HHK\API\OAuth\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClientEntity implements ClientEntityInterface {

    use ClientTrait;
    use EntityTrait;

     /**
     * @var bool
     */
    private $allowPlainTextPkce = false;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param string[] $redirectUri
     */
    public function setRedirectUri(array $redirectUri): void
    {
        $this->redirectUri = $redirectUri;
    }

    public function setConfidential(bool $isConfidential): void
    {
        $this->isConfidential = $isConfidential;
    }

    public function isPlainTextPkceAllowed(): bool
    {
        return $this->allowPlainTextPkce;
    }

    public function setAllowPlainTextPkce(bool $allowPlainTextPkce): void
    {
        $this->allowPlainTextPkce = $allowPlainTextPkce;
    }
    
}