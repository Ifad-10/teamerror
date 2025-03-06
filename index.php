<?php

function generate_sha256_hash($input_string) {
    return strtoupper(hash("sha256", $input_string));
}

function generate_random_device_id() {
    return strtoupper(hash("sha256", uniqid(mt_rand(), true)));
}

if (isset($_GET['pin']) && isset($_GET['num'])) {
    $password = $_GET['pin'];
    $username = $_GET['num'];

    $hashed_password = generate_sha256_hash($password);

    $api_response = get_refresh_token_and_userid($username, $hashed_password);

    if ($api_response && isset($api_response['refresh_token'], $api_response['userId'])) {
        make_second_api_request($api_response['refresh_token'], $api_response['userId'], $username);
    } else {
        echo "X-KM-REFRESH-TOKEN or userId not found in the response.";
    }
} else {
    echo "Please provide both pin and num using ?pin=YOUR_PIN&num=YOUR_NUM in the URL.";
}

function get_refresh_token_and_userid($username, $password) {
    $curl = curl_init();

    $payload = json_encode([
        "aspId" => "100012345612345",
        "mpaId" => null,
        "password" => $password,
        "username" => $username
    ], JSON_UNESCAPED_UNICODE);

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://app2.mynagad.com:20002/api/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Host: app2.mynagad.com:20002',
            'User-Agent: okhttp/3.14.9',
            'Connection: Keep-Alive',
            'Accept-Encoding: gzip',
            'Content-Type: application/json',
            'X-KM-UserId: 60452556',
            'X-KM-User-AspId: 100012345612345',
            'X-KM-User-Agent: ANDROID/1164',
            'X-KM-DEVICE-FGP: ' . generate_random_device_id(),
            'X-KM-Accept-language: bn',
            'X-KM-AppCode: 01',
            'Content-Type: application/json; charset=UTF-8',
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
        return null;
    } else {
        $refresh_token = null;
        $header_lines = explode("\r\n", $headers);
        foreach ($header_lines as $header) {
            if (stripos($header, 'X-KM-REFRESH-TOKEN:') === 0) {
                $refresh_token = trim(substr($header, 19));
            }
        }

        $data = json_decode($body, true);
        if ($refresh_token && isset($data['userId'])) {
            return [
                'refresh_token' => $refresh_token,
                'userId' => $data['userId']
            ];
        }

        return null;
    }
}

function make_second_api_request($auth_token, $user_id, $username) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://app2.mynagad.com:20002/api/external/kyc/customer-data-for-resubmit',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(["otp" => null, "phoneNumber" => $username], JSON_UNESCAPED_UNICODE),
        CURLOPT_COOKIE => 'WMONID=5TfbMnXgsQ1; TS01e66e4e=01e006cfdc3e32c99c4b63c77b820a203bf9d022483f1eccffbcf8ff9f716c34b29e8d9a35168ac652e91afb0242f3bf1c359a72561186e75535f9da541a97b74ab8bd1a28; JSESSIONID=Hsltl6S0WrgjOvR6-6hRUo7A2WIS3d-ExBGsXfIO',
        CURLOPT_HTTPHEADER => [
            'Host: app2.mynagad.com:20002',
            'User-Agent: okhttp/3.14.9',
            'Content-Type: application/json',
            'X-KM-UserId: ' . $user_id,
            'X-KM-User-MpaId: 17379475106370043002311446537520',
            'X-KM-User-AspId: 100012345612345',
            'X-KM-User-Agent: ANDROID/1164',
            'X-KM-Accept-language: bn',
            'X-KM-AUTH-TOKEN: ' . $auth_token,
            'X-KM-DEVICE-FGP: ' . generate_random_device_id(),
            'X-KM-AppCode: 01',
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $data = json_decode($response, true);

        if (isset($data['dob'])) {
            $dob = $data['dob'];
            $formatted_dob = substr($dob, 0, 4) . '-' . substr($dob, 4, 2) . '-' . substr($dob, 6, 2);
            $data['dob'] = $formatted_dob;
        }

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
?>