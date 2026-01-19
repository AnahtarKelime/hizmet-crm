<?php
/**
 * Google Maps API URL İmzalama Fonksiyonu
 * 
 * Bu fonksiyon, Google Maps Static API veya Street View API gibi servisler için
 * URL imzalama işlemini gerçekleştirir.
 * 
 * @param string $myUrlToSign İmzalanacak tam URL (API Key veya Client ID dahil)
 * @param string $privateKey Google Cloud Console'dan alınan URL Signing Secret (Base64)
 * @return string İmzalanmış URL
 */
function signGoogleMapsUrl($myUrlToSign, $privateKey) {
    // URL'i parçalarına ayır
    $url = parse_url($myUrlToSign);

    // İmzalanacak kısmı oluştur (path + query)
    $urlPartToSign = $url['path'] . "?" . $url['query'];

    // Private Key'i decode et (URL-safe Base64 -> Binary)
    $decodedKey = base64_decode(str_replace(array('-', '_'), array('+', '/'), $privateKey));

    // HMAC SHA1 ile imzayı oluştur
    $signature = hash_hmac("sha1", $urlPartToSign, $decodedKey, true);

    // İmzayı encode et (Binary -> URL-safe Base64)
    $encodedSignature = str_replace(array('+', '/'), array('-', '_'), base64_encode($signature));

    // Orijinal URL'e imzayı ekle
    return $myUrlToSign . "&signature=" . $encodedSignature;
}
?>