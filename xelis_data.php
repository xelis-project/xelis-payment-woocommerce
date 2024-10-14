<?php

class Xelis_Data
{
  public function get_today_price_usdt(): float
  {
    // TODO: maybe add more trusted source for price data
    $url = 'https://index.xelis.io/views/get_market_tickers_time(*)?count=true&limit=1&order=time::desc&param=86400&where=asset::eq::USDT';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
      throw new Exception(message: curl_error(handle: $ch));
    }

    curl_close($ch);
    $data = json_decode($response);

    if (isset($data->rows) && is_array($data->rows) && count($data->rows) > 0) {
      if (isset($data->rows[0]->price)) {
        if (is_numeric($data->rows[0]->price)) {
          return $data->rows[0]->price;
        }
      }
    }

    throw new Exception(message:"can't parse price from response");
  }

  public function convert_usd_to_xel(float $usd): float
  {
    $price = $this->get_today_price_usdt();
    return round($usd / $price, 8);
  }
}