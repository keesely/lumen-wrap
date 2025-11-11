<?php

class JwtTest extends TestCase {

  public function testBuild() {
    $id = uniqid();
    $example = [
      'headers' => ['typ' => 'JWT', 'alg' => 'HS256', 'kid' => '1234567890'],
      'claims' => ['jti' => $id, 'sub' => '1234567890', 'name' => 'John Doe', 'admin' => true],
      'signature' => 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY',
    ];

    $jwt = new Lx\Support\Jwt;

    $signature = $example['signature'];
    $payload = $example['claims'];
    $token = $jwt->sign($signature);
    $token->setId($id);
    $token->setSubject($payload['sub']);
    $token->set('name', $payload['name']);
    $token->set('admin', $payload['admin']);
    foreach ($example['headers'] as $key => $value) {
      $token->withHeader($key, $value);
    }

    Log::channel('stderr')->info('Token: '.(string) $token);

    $token1 = (clone $token)->toString();
    Log::channel('stderr')->info('Token1: '.(string) $token1);

    $token2 = (clone $token)->build()->toString();
    Log::channel('stderr')->info('Token2:'.(string) $token2);
    $this->assertEquals($token1, $token2, (string) $token);

    $build = (new Lx\Support\Jwt)->NewBuilder([
      'headers' => $example['headers'],
      'signer' => ['HS256', $signature],
      ...$payload
    ])->toString();
    Log::channel('stderr')->info('Build:' . (string) $build);
    $this->assertEquals($token1, $build);

    $build2 = (new Lx\Support\Jwt)
      ->sign($signature)
      ->withHeaders($example['headers'])
      ->withClaims($payload);
    Log::channel('stderr')->info(($build2->toString()));
    $this->assertEquals($token1, $build2->toString());

    $build3 = (string) (new Lx\Support\Jwt)->build()
                                  ->sign($signature)
                                  ->withHeaders($example['headers'])
                                  ->withClaims($payload);
    Log::channel('stderr')->info((string) $build3);
    $this->assertEquals($token1, $build3);

    return [$token1, $example];
  }

  public function testParse() {
    [$token, $example] = $this->testBuild();

    $jwt = new Lx\Support\Jwt;

    $parsed = $jwt->parse($token);

    $signature = $example['signature'];
    unset($example['signature']);
    $example['token'] = $token;

    $this->assertEquals($parsed->toArray(), $example);
    $this->assertTrue($parsed->isValid($signature));
    $this->assertFalse($parsed->isValid($signature.'0'));

    $this->assertTrue(
      (new Lx\Support\Jwt)->Verify($token, $signature, false)
    );
  }

  public function testExpired() {
    $token = (new Lx\Support\Jwt)
      ->sign('secret')
      ->set('exp', time()-1)
      ->getToken();

    $token2 = (new Lx\Support\Jwt)
      ->sign('secret2')
      ->setExpiration(now()->addMinutes(1))
      ->getToken();

    $token3 = (new Lx\Support\Jwt)
      ->sign('secret2')
      ->expiresAt(date('Y-m-d H:i:s', time()-1))
      ->getToken();

    $token4 = (new Lx\Support\Jwt)
      ->sign('secret2')
      ->getToken();

    $parse = (new Lx\Support\Jwt)->parse($token);
    $parse2 = (new Lx\Support\Jwt)->parse($token2);
    $parse3 = (new Lx\Support\Jwt)->parse($token3);
    $parse4 = (new Lx\Support\Jwt)->parse($token4);

    $this->assertTrue($parse->isExpired()[0]);
    $this->assertFalse($parse2->isExpired()[0]);
    $this->assertTrue($parse3->isExpired()[0]);
    $this->assertFalse($parse3->get('exp') == null);
    $this->assertTrue($parse4->isExpired()[0], null == $parse4->get('exp'));

    $this->assertFalse($parse->isValid('secret2'));
    $this->assertTrue($parse2->isValid('secret2'));
    $this->assertTrue($parse3->isValid('secret2'));
    $this->assertTrue($parse4->isValid('secret2'));
  }
}
