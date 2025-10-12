<?php
class JWT {
    private $secret_key = "mysupersecretkey";
    private $algorithm = "HS256";

    public function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode($payload);
        
        $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $this->secret_key, true);
        $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64_header . "." . $base64_payload . "." . $base64_signature;
    }

    public function decode($jwt) {
        $token_parts = explode('.', $jwt);
        
        if (count($token_parts) != 3) {
            return false;
        }
        
        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[0]));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1]));
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[2]));
        
        $expected_signature = hash_hmac('sha256', $token_parts[0] . "." . $token_parts[1], $this->secret_key, true);
        
        if (!hash_equals($signature, $expected_signature)) {
            return false;
        }
        
        return json_decode($payload, true);
    }
}
?>
