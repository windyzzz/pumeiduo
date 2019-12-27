<?php
$config = array (
		//应用ID,您的APPID。
		'app_id' => "2017060107396737",

		//商户私钥，您的原始格式RSA私钥
		'merchant_private_key' => "MIIEowIBAAKCAQEA3ZVADSNNblSOufqDDhDIGtqPl1YJEzyddqhr0wCIMlT7rJh6
ghvOV8aQGzstMUuQhyObgmKUlrw0gB3s0W2CHj4SQX9MeL5qCTrEAVOtA8Z4IN4m
KvPGN8G+y/1J8GTQ5HABGm+9tFmVvmPjFV7mKi8sMFqA6yPlwDxYgaspUOxT9/MC
Ldo3Noq7h8ecdWfHDnvI2ikfurVKwp2ZaI4ZGkjmPAzM1mgNq8VmInB8dVUmiocX
20hDnXfru7LPA+98Dcrjqq9khTaywLVLo1sZb3FrJJWvBUsMEhJ1gJAtOMY6nP9X
rpuxVGt0sb5oFngoI9OCdvM3v9USnC3uSNLlswIDAQABAoIBABO5l4wT2m655EK2
BDiaUdXiIuor5H7r5HCNqZuM7pLccdL5d95hL0stB+MEr4811NXS26MNt4B7nIjT
ISO7hdu/Vsyx0lLlUHcl3hDoK/ysDEGQxQEJ1llcS+nI0G4v61CKj+6Uh+SoHOZn
6e0bF44lyN89D0DfXzJvrMlOPU1Qse4dZfE0f/pAKW26EgG6ttC9lidnfYZF+ntl
U6f+xg60aHh7nERQd9ddnb6WbwTgYxUcf6COf6TO4F70MzK9q1at1kp/sHEUmnAS
FBnkDOsnFjXB9wgBescNH3Dvyn0Hai/R+y3DIWtnTrUs394GSp+FIkG5PEV6sOat
ItQ+6yECgYEA+VSseyAWvyKLmkUBgmvg/+ZkCllPSYBQKrNvmlz7iiTeoYjnnvDH
lDSRogpal6GUll2QM0KUZnlmTovFJYEjU9bfRGKvQDuyInSaGCnkYLNH0IYyqgRO
upZBZmDCn92Vg93lFIF97SGe3D1t4YjLKxVDPKiCNcfLXTPV540c68kCgYEA44KR
6X7eXCMV38u0H0/Krm6MKg4pkjkwLAW1t4ymkNMUeVFJp7aN8zKvMpOlUDCKxGpq
cTtRpmh/91Nb4kBS9IH+GOdgSXbYOK6NSIneVRKx7Obeun7mm1r/jQ8pdq2DxUK8
wK+xEODiy1W0Q7pAZjQMYIk4UGVvJORv1NL6i5sCgYBNxSWPujCeKc5VrlSMM84Y
w+pMeBMNICLhTtru0TX8nwd6Z1On9f6qscMDQiuDxHiudjy2YHwdgpxwv5Qc4Kz+
R7WYhReY94XWzXwLMXX027b4ygMfmPxdouR/ZIsQhnNOkHYV8VYwEx6UH+0YPizx
IU65qu4CjHFYfwSnlxKAUQKBgQDL6hfF5ISAqKHOeNH0zpunREY024S/HqriiiuZ
XGNeoxJSulz+CU2pkOAewN8GxLtb2NWWr8g7Eqa/kuNkIqs3o9uPjrJqpi6efzT6
szenoJ4A69zt4xfmXuV2FQTg7hyRDYQIYHCf5DDiduqfWaym7je5vsPOq1u3AViK
tJ8DyQKBgFR0niYnAgWcwpl3po1ZhoP0VQjGe/qvjLbZA+sfTFh9Of5EJOXhk/c2
tvy1B80wWiiPdffk3xXTJQu8CHBxffFctExcPlXO5+Vm74yzixzTPLFm5yV+1/sp
RkbJY7hr9M+Epa46oQKz+yM6phdZo4er8oMlg7tmTgxRdH/tNsGx",

		//异步通知地址
		'notify_url' => "http://工程公网访问地址/alipay.trade.wap.pay-PHP-UTF-8/notify_url.php",

		//同步跳转
		'return_url' => "http://mitsein.com/alipay.trade.wap.pay-PHP-UTF-8/return_url.php",

		//编码格式
		'charset' => "UTF-8",

		//签名方式
		'sign_type'=>"RSA2",

		//支付宝网关
		'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

		//支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
		'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjpJVbOXeVQAErBl3fBMSFUHIc41IeuOIxXJ0SFIGnLK1qqJFsjuyfg6B2OAORXzs4B1i+o2bv9+YA+t3KyUbppAtnd/2Ee3ERDUhjUTmDSwyGhtpb31yiOdul2RQNp5ioZ7Gth0ar9J7ULhe5AyJmaLJo3nVVIPqtz6K+DjqD0ccdaJmjoqAYRF3QMQraR4WlEF8Kglv1hCUBgHekYlWQMvtFHuwRHPGdoXabKK3JbL24a0/I7X1AVjJj/LTOfKt9jFX8xDtXHSRnyIjfwAJL34F+2ODbWxCFxU8pt+k3K0d7ZHcP0xurRsRCJqaOJKnqg7ipt9SXunbucCPrRbn2QIDAQAB",


);
