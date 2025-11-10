<?php

/**
 * @fileName Support/Jwt.php
 * @category php
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com>
 * @since 2025.11.10
 * */

namespace Lx\Support;

use ArrayAccess;
use Stringable;

use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use DateTimeImmutable;

class Jwt implements Arrayable, ArrayAccess, Stringable
{

  const SIGNER_SHA256 = Signer\Hmac\Sha256::class;
  const SIGNER_SHA384 = Signer\Hmac\Sha384::class;
  const SIGNER_SHA512 = Signer\Hmac\Sha512::class;
  const SIGNER_RS256 = Signer\Rsa\Sha256::class;
  const SIGNER_RS384 = Signer\Rsa\Sha384::class;
  const SIGNER_RS512 = Signer\Rsa\Sha512::class;
  const SIGNER_ES256 = Signer\Ecdsa\Sha256::class;
  const SIGNER_ES384 = Signer\Ecdsa\Sha384::class;
  const SIGNER_ES512 = Signer\Ecdsa\Sha512::class;

  const RESERVED_CLAIMS = ['jti', 'iss', 'iat', 'aud', 'exp', 'nbf', 'sub'];

  protected $headers = ['typ' => 'JWT', 'alg' => 'HS256'];

  protected $claims = [];

  protected $signature = [];


  protected $_signers = [
    'sha256'   => self::SIGNER_SHA256,
    'sha384'   => self::SIGNER_SHA384,
    'sha512'   => self::SIGNER_SHA512,
    'HS256'    => self::SIGNER_SHA256,
    'HS384'    => self::SIGNER_SHA384,
    'HS512'    => self::SIGNER_SHA512,
    'RS256'    => self::SIGNER_RS256,
    'RS384'    => self::SIGNER_RS384,
    'RS512'    => self::SIGNER_RS512,
    'ES256'    => self::SIGNER_ES256,
    'ES384'    => self::SIGNER_ES384,
    'ES512'    => self::SIGNER_ES512
  ];

  protected $aliases = [
    'jti'           => 'identifiedBy',
    'iss'           => 'issuedBy',
    'iat'           => 'issuedAt',
    'aud'           => 'permittedFor',
    'exp'           => 'expiresAt',
    'nbf'           => 'canOnlyBeUsedAfter',
    'sub'           => 'relatedTo',
  ];

  protected $allows = [
    'jti'           => 'identifiedBy',
    'setId'         => 'identifiedBy', // jti
    'getId'         => 'jti',

    'iss'           => 'issuedBy',
    'setIssuer'     => 'issuedBy', // iss
    'getIssuer'     => 'iss',

    'iat'           => ['issuedAt', 'formatTime'],
    'setIssuedAt'   => ['issuedAt', 'formatTime'], // iat
    'issuedAt'      => ['issuedAt', 'formatTime'],
    'getIssuedAt'   => 'iat',

    'aud'           => 'permittedFor',
    'setAudience'   => 'permittedFor', // aud
    'getAudience'   => 'aud',

    'exp'           => ['expiresAt', 'formatTime'],
    'setExpiration' => ['expiresAt', 'formatTime'], // exp
    'setExpires'  => ['expiresAt', 'formatTime'],
    'expiresAt'     => ['expiresAt', 'formatTime'],
    'getExpiration' => 'exp',

    'nbf'           => ['canOnlyBeUsedAfter', 'formatTime'],
    'setNotBefore'  => ['canOnlyBeUsedAfter', 'formatTime'], // nbf
    'canOnlyBeUsedAfter' => ['canOnlyBeUsedAfter', 'formatTime'],
    'getNotBefore'  => 'nbf',

    'sub'           => 'relatedTo',
    'setSubject'    => 'relatedTo', // sub
    'getSubject'    => 'sub',
  ];


  protected $builder;

  protected $parser;

  protected $token;

  /**
   * Get signers supported
   *
   * @return array
   * */
  public function getSigners () {
    return $this->_signers;
  }

  /**
   * Get signer by key
   *
   * @param Lcobucci\JWT\Signer | string $signer
   *
   * @return Lcobucci\JWT\Signer
   * */
  public function getSigner ($key) {
    if (is_object($key) && $key instanceof Signer) return $key;
    if (class_exists($key)) return new $key;
    if ($signer = ($this->_signers[$key] ?? false)) return new $signer;
    return false;
  }

  /**
   * Parse Key to signer
   *
   * @param string $key
   *
   * @return Lcobucci\JWT\Signer\Key\InMemory
   * */
  protected function parseKey ($key) {
    if (is_object($key) && $key instanceof Signer\Key) return $key;
    if (is_file($key) && file_exists($key)) return InMemory::file($key);
    return InMemory::plainText(str_pad($key, 32, "\0"));
  }

  /**
   * Convert Time| DateTime| Carbon to DateTimeImmutable
   *
   * @param mixed $value
   *
   * @return DateTimeImmutable
   * */
  protected function formatTime($value): ?DateTimeImmutable {
    switch (gettype($value)) {
    case 'integer':
      return new DateTimeImmutable("@{$value}");
    case 'string':
      return new DateTimeImmutable($value);
    case 'object':
      if ($value instanceof DateTimeInterface) return $value;
      if ($value instanceof Carbon) return $value->toDateTimeImmutable();
      return $value;
    }
  }

  /**
   * Build JWT token
   *
   * @return Lx\Support\Jwt
   * */
  protected function build() {
    return $this->withHeaders($this->headers)
                ->withClaims($this->claims);
  }


  /**
   * Build JWT with signer and key
   * 
   * @param Lcobucci\JWT\Signer $signer
   * @param string $signkey
   *
   * @return Lx\Support\Jwt
   * */
  public function sign($key, $signer = 'HS256') {
    $builder = $this->getBuilder();
    if (!$signer = $this->getSigner($signer))
      throw new JwtTokenException('Invalid signer');

    if (!in_array(get_class($signer), $this->getSigners())) 
      throw new JwtTokenException('Unsupported signer');

    $this->signature = [$this->parseKey($key), $signer];
    $this->headers['alg'] = $signer->algorithmId();
    return $this; 
  }

  /**
   * Get JWT token string
   *
   * */
  public function getToken(): string {
    @[$key, $signer] = array_pad($this->signature?: [], 2, '');
    $signer = $this->getSigner($signer ?: 'HS256');
    if (!$this->token) {
      $this->token = $this->build()
                          ->getBuilder()
                          ->getToken($signer, $this->parseKey($key));
    }
    return $this->token->toString();
  }

  public function getBuilder() {
    return $this->builder ?: $this->NewBuilder()->builder;
  }

  public function getParser() {
    return $this->parser;
  } 

  public function toString(): string {
    if ($this->builder) return $this->getToken();
    else if ($this->parser) return $this->parser->toString();
    throw new JwtTokenException('Token not found');
  }

  public function toArray(): array {
    return [
      'headers' => $this->headers,
      'claims' => $this->claims,
      'token' => $this->toString(),
    ];
  }

  public function getHeaders(): array {
    return $this->headers ?: [];
  }

  public function getClaims(): array {
    return $this->claims ?: [];
  }

  public function getHeader(string $name): mixed {
    return Arr::get($this->headers, $name);
  }

  public function getClaim(string $name): mixed {
    return Arr::get($this->claims, $name);
  }

  public function getSignature(): array {
    return $this->signature ?: [];
  }

  public function withHeaders(array $headers): Jwt {
    foreach ($headers as $key => $value) {
      $this->withHeader($key, $value);
    }
    return $this;
  }

  public function withHeader(string $name, mixed $value): Jwt {
    $builder = $this->getBuilder();
    $this->builder = $builder->withHeader($name, $value);
    $this->headers[$name] = $value;
    return $this;
  }

  public function withClaims(array $claims): Jwt {
    foreach ($claims as $key => $value) {
      $this->withClaim($key, $value);
    }
    return $this;
  }

  public function withClaim(string $name, mixed $value): Jwt {
    $builder = $this->getBuilder();
    if ($set = $this->allows[$name] ?? null) {
      if (is_array($set)) {
        [$set, $formatter] = $set;
        if (method_exists($this, $formatter)) {
          $value = $this->$formatter($value);
        }
      }
      $this->builder = $builder->$set($value);

      if ($key = array_search($set, $this->aliases)) {
        $this->set($key, $value);
      }
      return $this;
    }
    $this->builder = $builder->withClaim($name, $value);
    $this->set($name, $value);
    return $this;
  }


  /**
   * Build JWT token
   * 
   * @param array $options
   * 
   * @return Lx\Support\Jwt
   * */
  public function NewBuilder (array $options = []) {
    $this->builder = new Builder(new JoseEncoder, ChainedFormatter::default());

    $jti = $options['id'] ?? $options['jti'] ?? uniqid();
    $this->withClaim('setId', $jti);

    if ($aud = $options['aud'] ?? null) $this->withClaim('aud', $aud);
    if ($iss = $options['iss'] ?? null) $this->withClaim('iss', $iss);
    if ($iat = $options['iat'] ?? null) $this->withClaim('iat', $iat);
    if ($sub = $options['sub'] ?? null) $this->withClaim('sub', $sub);
    if ($exp = $options['exp'] ?? null) $this->withClaim('exp', $exp);

    if (is_array($headers = $options['headers'] ?? null)) {
      $this->withHeaders($headers);
    }

    $data = Arr::except($options, [
      'id', 'jti', 'aud', 'iss', 'iat', 'sub', 'exp', 'headers',
      'signer',
    ]);
    if (count($data) > 0) $this->withClaims($data); 

    $signer = $options['signer'] ?? null;
    if ($signer) {
      @[$signer, $key] = is_array($signer) ? $signer : ['HS256', $signer];
      if ($signer && $key) $this->sign($key, $signer);
    }

    return $this;
  }

  /**
   * Parse JWT token
   * */
  public function Parse ($token, array $options = []) {
    try {
      $this->parser = new Parser(new JoseEncoder)->parse(trim($token));

      $verify = $options['verify'] ?? [];
      @[$signer, $key] = is_array($verify) ? $verify : [$verify, ''];
      if ($signer && $key && !$this->isValid($signer, $key)) return false;

      $this->builder = null;
      $this->headers = $this->parser->headers()->all();
      $this->claims = $this->parser->claims()->all();

      return $this;
    } catch (\Exception $e) {
      throw new JwtTokenException($e->getMessage(), $e->getCode());
    }
  }


  /**
   * Check if token is expired
   * 
   * @param mixed $time (int|DateTimeInterface|Carbon|string)
   * 
   * @return Array [bool, int]
   * */
  public function isExpired($time = null): array {
    $time = $this->formatTime(is_null($time) ? time() : $time);

    $exp = $this->get('exp');
    return [$exp < $time, $exp];
  }

  public function isValid($key, $strict = false) {
    $signer = $this->getSigner($this->getHeader('alg'));
    if (!$signer) throw new JwtTokenException('Invalid signer');
    $key = $this->parseKey($key);

    try {
      (new Validator)->assert(
        $this->parser, 
        new Constraint\SignedWith($signer, $key)
      );
      $this->signature = [$key, $signer];
      return true;
    }
    catch (\Exception $e) {
      if ($strict) throw $e;
      return false;
    }
  }

  public function Verify($token, $key) {
    try {
      $parse = (new Parser(new JoseEncoder))->parse(trim($token));
      $alg = $parse->headers()->get('alg');
      (new Validator)->assert(
        $parse,
        new Constraint\SignedWith(new ($this->getSinger($alg)), $this->parseKey($key))
      );
      return $parse;
    } catch (\Exception $e) {
      if ($e instanceof Validation\RequiredConstraintsViolated) {
        $e = $e->violations[0] ?? $e;
      }
      throw $e;
    }
  }

  public function isJwt($token) {
    try {
      (new Parser(new JoseEncoder))->parse(trim($token));
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }

  public function set(string $name, mixed $value): Jwt {
    $this->claims[$name] = $value;
    return $this;
  }

  public function get(string $name, $default = null): mixed {
    return Arr::get($this->claims, $name, $default);
  }

  public function has(string $name): bool {
    return Arr::has($this->claims, $name);
  }

  public function remove(string $name): Jwt {
    Arr::forget($this->claims, $name);
    return $this;
  }

  public function offsetSet(mixed $name, mixed $value) {
    $this->set($name, $value);
    return $value;
  }

  public function offsetGet(mixed $name) {
    return $this->get($name);
  }

  public function offsetExists(mixed $name) {
    return $this->has($name);
  }

  public function offsetUnset(mixed $name) {
    return $this->remove($name);
  }

  public function __set(string $name, mixed $value) {
    return $this->offsetSet($name, $value);
  }

  public function __get(string $name) {
    return $this->offsetGet($name);
  }

  public function __toString() {
    return $this->toString();
  }

  public function __call($name, $args) {
    if ($this->builder && ($builder = $this->allows[$name] ?? null)) {
      return call_user_func_array([$this, 'withClaim'], array_merge([$name], $args));
    }
    if ($this->parser && ($parser = $this->allows[$name] ?? null)) {
      return call_user_func_array([$this, 'getClaim'], array_merge([$parser], $args));
    }
    if ($this->builder && method_exists($this->builder, $name)) {
      return call_user_func_array([$this, $name], $args);
    }
    if ($this->parser && method_exists($this->parser, $name)) {
      return call_user_func_array([$this, $name], $args);
    }
    if (method_exists($this, $name)) {
      return call_user_func_array([$this, $name], $args);
    }
    throw new JwtTokenException('Method ('.$name.') not found');
    //return $this;
  }
}

class JwtTokenException extends \Exception {}
