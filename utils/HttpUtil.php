<?php namespace utils;


class HttpUtil{

    public  function __construct(){


    }
 public function ci (){
    return get_instance();
 }
 public function curlsendHttpPost($endpoint,$headers,$body){
    $url=DATA_URL.$endpoint;
    $ch = curl_init($url);

     //post values
    curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
    // Option to Return the Result, rather than just true/false
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    // Set Request Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers
    );
    //time to wait while waiting for connection...indefinite
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

    curl_setopt($ch,CURLOPT_POST,1);
    //set curl time..processing time out
    curl_setopt($ch, CURLOPT_TIMEOUT, 200);
    // Perform the request, and save content to $result
    $result = curl_exec($ch);
      //curl error handling
      $curl_errno = curl_errno($ch);
              $curl_error = curl_error($ch);
              if ($curl_errno > 0) {
                     curl_close($ch);
                    return  "CURL Error ($curl_errno): $curl_error\n";
                  }
        $info = curl_getinfo($ch);
       curl_close($ch);
       $decodedResponse =json_decode($result);
       return $decodedResponse;
}

public function curlgetHttp($endpoint,$headers,$body){
    $url=DATA_URL.$endpoint;
    $ch = curl_init($url);

     //post values
    // curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
    // Option to Return the Result, rather than just true/false
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    // Set Request Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers
    );
    //time to wait while waiting for connection...indefinite
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

    // curl_setopt($ch,CURLOPT_POST,1);
    //set curl time..processing time out
    curl_setopt($ch, CURLOPT_TIMEOUT, 200);
    // Perform the request, and save content to $result
    $result = curl_exec($ch);
      //curl error handling
      $curl_errno = curl_errno($ch);
              $curl_error = curl_error($ch);
              if ($curl_errno > 0) {
                     curl_close($ch);
                    return  "CURL Error ($curl_errno): $curl_error\n";
                  }
        $info = curl_getinfo($ch);
       curl_close($ch);
       $decodedResponse =json_decode($result);
       return $decodedResponse;
}
public function curlgetHttpauth($endpoint, $headers, $username, $password) {
    $url = $endpoint;
    $ch = curl_init($url);

    // Post values (if needed)
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    // Option to Return the Result, rather than just true/false
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Set Request Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Basic Authentication
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

    // Time to wait while waiting for connection...indefinite
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

    // Set cURL timeout and processing timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 200);

    // Perform the request, and save content to $result
    $result = curl_exec($ch);

    // cURL error handling
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    if ($curl_errno > 0) {
        curl_close($ch);
        return "CURL Error ($curl_errno): $curl_error\n";
    }

    $info = curl_getinfo($ch);
    curl_close($ch);

    $decodedResponse = json_decode($result);
    return $decodedResponse;
}
	
  

}



?>