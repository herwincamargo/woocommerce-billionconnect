<?php

// Clase de sincronización de la API BillionConnect
class BC_API_Connector {

    private $api_url;
    private $app_key;
    private $app_secret;

    public function __construct($is_production = false) {
        $this->api_url = $is_production 
            ? 'https://api-flow.billionconnect.com/Flow/saler/2.0/invoke' 
            : 'https://api-flow-ts.billionconnect.com/Flow/saler/2.0/invoke';
        $this->app_key = 'your_app_key';
        $this->app_secret = 'your_app_secret';
    }

    public function synchronize_countries() {
        $request_body = [
            'tradeType' => 'F001',
            'tradeTime' => date('Y-m-d H:i:s'),
            'tradeData' => [
                'salesMethod' => 'param_value'
            ]
        ];

        $signature = $this->generate_signature($request_body);

        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'x-channel-id: ' . $this->app_key,
            'x-sign-method: md5',
            'x-sign-value: ' . $signature
        ];

        $response = $this->post_request($this->api_url, $headers, $request_body);

        // Procesar respuesta
        if ($response) {
            // Lógica para manejar países sincronizados
        }
    }

    public function synchronize_products() {
        $request_body = [
            'tradeType' => 'F002',
            'tradeTime' => date('Y-m-d H:i:s'),
            'tradeData' => []
        ];

        $signature = $this->generate_signature($request_body);

        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'x-channel-id: ' . $this->app_key,
            'x-sign-method: md5',
            'x-sign-value: ' . $signature
        ];

        $response = $this->post_request($this->api_url, $headers, $request_body);

        if ($response) {
            // Procesar y almacenar productos
        }
    }

    private function generate_signature($body) {
        $plain_text = $this->app_secret . json_encode($body);
        return md5($plain_text);
    }

    private function post_request($url, $headers, $body) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($http_code !== 200) {
            // Manejar error HTTP
        }

        curl_close($curl);

        return json_decode($response, true);
    }
}