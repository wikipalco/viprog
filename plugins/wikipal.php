<?php
$pluginData[wikipal][type] 						= 'payment';
$pluginData[wikipal][name] 						= 'پرداخت آنلاین با <a href="https://wikipal.co" target="_blank">ویکی پال</a>';
$pluginData[wikipal][uniq] 						= 'wikipal';
$pluginData[wikipal][note] 						= 'wikipal';
$pluginData[wikipal][description] 				= '';
$pluginData[wikipal][author][name] 				= 'تیم توسعه ویکی پال';
$pluginData[wikipal][author][url] 				= 'https://wikipal.co';
$pluginData[wikipal][author][email] 			= 'info@wikipal.co';
$pluginData[wikipal][field][config][1][title] 	= 'لطفا مرچنت کد خود را در فیلد زیر وارد نمایید ';
$pluginData[wikipal][field][config][1][name] 	= 'merchant';

function gateway__wikipal($data)
{
	global $db;
	
	$MerchantID 			= $data[merchant];
	$Price 					= $data[amount] / 10;
	$Description 			= "تراکنش ". $data[invoice_id];
	$InvoiceNumber 			= $data[invoice_id];
	$CallbackURL 			= $data[callback];

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentRequest.php');
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
	curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$MerchantID&Price=$Price&Description=$Description&InvoiceNumber=$InvoiceNumber&CallbackURL=". urlencode($CallbackURL));
	curl_setopt($curl, CURLOPT_TIMEOUT, 400);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$result = json_decode(curl_exec($curl));
	curl_close($curl);

	if ($result->Status == 100){
		
		$au = $result->Authority;
		$update[payment_rand] = $au;
		$sql = $db->prepare("UPDATE `payment` SET `payment_rand` = ? WHERE `payment_rand` = ? LIMIT 1");
		$sql->execute(array (
			$update[payment_rand],
			$invoice_id
		));

		redirect_to("http://gatepay.co/webservice/startPayment.php?au=$au");
	} else {
		$error = $result->Status;
		$data[title] 	= 'خطای سیستم';
		$data[message] 	= '<font color="red">در ارتباط با درگاه ویکی پال مشکلی به وجود آمده است. لطفا مطمئن شوید کد مرچنت کد خود را به درستی در قسمت مدیریت وارد کرده اید.</font> شماره خطا: '.$error.'<br /><a href="index.php" class="button">بازگشت</a>';
		return $data;
		exit;
	}
}

function callback__wikipal($data)
{
	global $db,$get;
	
	$MerchantID 		= $data[merchant];
	$Authority 			= $_POST['authority'];
	$InvoiceNumber 		= $_POST['InvoiceNumber'];
	
	$sql 				= 'SELECT * FROM `payment` WHERE `payment_rand` = "'.$InvoiceNumber.'" LIMIT 1;';
	$payment 			= $db->query($sql)->fetch();

	$Price 				= $payment[payment_amount] / 10;

	if ($_POST['status'] == 1) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentVerify.php');
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
		curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$MerchantID&Price=$Price&Authority=$Authority");
		curl_setopt($curl, CURLOPT_TIMEOUT, 400);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = json_decode(curl_exec($curl));
		curl_close($curl);

		if ($result->Status == 100) {
			$output[status]		= 1;
			$output[res_num]	= $Authority;
			$output[ref_num]	= $result->RefCode;
			$output[payment_id]	= $payment[payment_id];
		} else {
			$output[status]		= 0;
			$output[message] 	= 'خطا در پرداخت, کد خطا : '. $result->Status;
		}
	} else {
		$output[status]		= 0;
		$output[message] 	= 'تراکنش لغو شده است';
	}
	return $output;
}