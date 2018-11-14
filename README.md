# s3-get-with-range-php

S3のGETリクエストで `Content-Range` ヘッダーの検証を行います。

## 下準備

1. `composer install` でdependenciesを解決します
2. 次にテスト用のCSVを作ります。以下を実行して下さい:

  ```
  $ php create_large_csv.php > ./awesome.csv
  ```

3. このコードでは実際のAmazon S3は使っておらず、[minio](https://github.com/minio/minio)を使っているので起動します:

  ```
  $ docker run --rm -e MINIO_ACCESS_KEY=MINIO4DEV -e MINIO_SECRET_KEY=MINIO4DEV -p 9000:9000 minio/minio server /data
  ```

  (オプションは各自に任せます)

4. 続いてAWS CLIでバケットを作り、先程のS3をアップロードします:

  ```
  $ export AWS_ACCESS_KEY_ID=MINIO4DEV; export AWS_SECRET_ACCESS_KEY=MINIO4DEV
  {
      "Location": "/test"
  }

  $ aws --endpoint-url http://127.0.0.1:9000 s3api put-object --bucket test --key awesome.csv --body ./awesome.csv
  {
      "ETag": "\"965b5c82c2541da6ee5112c640aaed31\""
  }
  ```

これで準備完了です。

## テストする

