<?php
namespace HHK\Enums;

enum SAMLCerts {
    case idpSign;
    case idpSign2;
    case idpEncryption;
    case idpEncryption2;
    case sp;
    case sprollover;
    case any;
}