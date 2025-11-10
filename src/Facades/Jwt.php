<?php

/**
 * 
 * @fileName JWTManage.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 29/05/2018
 * @version JWTManage.php 2018.05.29
 * */
namespace Lx\Facades;

//use Lcobucci\JWT\Builder as JWTAuth;
//use Lcobucci\JWT\ValidationData;

use Illuminate\Support\Facades\Facade;

use DateTimeImmutable;
use Lcobucci\Clock\FrozenClock;

use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Encoding\ChainedFormatter;

use Lcobucci\JWT\JwtFacade;

use Lcobucci\JWT\Validation\Validator;
use Lcobucci\JWT\Validation\Constraint;

use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;

class Jwt extends Facade {

  const SIGNER_SHA256 = Signer\Hmac\Sha256::class;
  //const SIGNER_KEYCHAIN = Signer\Key\InMemory::class;
  //const SIGNER_KEYCHAIN = Signer\Keychain::class;

  // Sha Rsa Alg
  const SIGNER_RS256 = Signer\Rsa\Sha256::class;
  const SIGNER_RS384 = Signer\Rsa\Sha384::class;
  const SIGNER_RS512 = Signer\Rsa\Sha512::class;

  protected $_parse;

  protected $_builder;

  protected $_token;

  protected $_signers = [];

  public function __construct () {
    $this->_signers = [
      'sha256'   => self::SIGNER_SHA256,
      //'keychain' => self::SIGNER_KEYCHAIN,
      'RS256'    => self::SIGNER_RS256,
      'RS384'    => self::SIGNER_RS384,
      'RS512'    => self::SIGNER_RS512,
    ];
  }

  /**
   * Get the registered name of the component.
   *
   * @return string
   */
  protected static function getFacadeAccessor()
  {
    return 'jwt';
  }

  public function Auth ($token = null) {
    if (empty($token)) 
      return new Builder(new JoseEncoder, ChainedFormatter::default());

    try {
      $parse = (new Parser(new JoseEncoder))->parse(trim($token));

      $issue = new Builder(new JoseEncoder, ChainedFormatter::default());
      $alg = $this->getSigner('sha256');

      //$keyStr = 'eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9';
      $keyStr = 'secret';
      $keyStr = $this->signkeyPadding($keyStr);
      $key = InMemory::plainText($keyStr);
      $now = now();

      $issue
        ->identifiedBy(uniqid()) // claim: jti
        ->permittedFor('localhost') // claim: aud
        ->issuedAt($now->toDateTimeImmutable()) // claim: iat
        ->issuedBy('localhost') // claim: iss
        ->relatedTo('Job Hub') // claim: sub
        ->expiresAt($now->addDays(1)->toDateTimeImmutable()); // claim: exp

      $token = $issue->getToken($alg, $key)->toString();

      //dd($token, $parse);

      $validator = new Validator;
      $validator->assert(
        (new Parser(new JoseEncoder))->parse(trim($token)),
        new Constraint\SignedWith(
          new $this->_signers['sha256'],
          Signer\Key\InMemory::plainText($keyStr.'1')
        )
      );
      dd(1, $token, $parse);
      //$this->_parse = (new Parser)->parse(trim($token));
      return $this;
  }
    catch (Throwable | \Exception $th) {
      dd($th, $parse->claims());
      return false;
    }
  }

  public function test() {
    $builder = $this->builder();
    $builder->withHeader('kid', 'kid');
    $builder->withClaim('alg', 'alg');
    
    $jwt->setId($this->ticket);
    $jwt->setIssuer(config('app.name'));
    $jwt->setIssuedAt($time);
    $jwt->setAudience($user->id);
    $jwt->setExpiration($exp);
    if ($this->get('social') && $social = Social::find($this->get('social'))) {
      $jwt->set('ov_id', $social->account);
    }
    $jwt->sign(JWT::getSigner('RS256'), $sec);

    $auth = $this->parse($jwt);
    $auth->isExpired();
    $auth->isValid();

    $auth->getId();
    $auth->getIssuer();
    $auth->getAudience();
    $auth->getSubject();
    $auth->get('ov_id');
    return $jwt;
  }


}
