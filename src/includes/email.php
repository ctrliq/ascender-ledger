<?php

use PHPMailer\PHPMailer;

function send_email($to, $subject, $message) {
	$from     = read_setting('email_from', 'ledger@ascender.local');
	$fromname = read_setting('email_fromname', 'Ascender Ledger');

	if (read_setting('disable_email', 0)) {
		return true;
	}

	if (!is_array($to) && !strstr($to, '@')) {
		return;
	}

	if (is_array($to)) {
		foreach ($to as $t) {
			if (trim($t) != '') {
				add_email_queue($t, $from, $fromname, $subject, $message);
			}
		}
	} else {
		add_email_queue($to, $from, $fromname, $subject, $message);
	}
}

function add_email_queue($to, $from, $fromname, $subject, $message) {
	if (read_setting('disable_email', 0)) {
		return true;
	}

	if (!strstr($to, '@')) {
		return;
	}

	db_execute_prepare('INSERT INTO `mail_queue` (`to`, `from`, `fromname`, `subject`, `message`, `created`) VALUES (?, ?, ?, ?, ?, ?)',
			array($to, $from, $fromname, $subject, $message, time()));
}

function process_mail_queue($newonly = false) {
	if ($newonly) {
		$emails = db_fetch_assocs('SELECT * FROM `mail_queue` WHERE `status` = 0');
	} else {
		$emails = db_fetch_assocs('SELECT * FROM `mail_queue`');
	}

	if (!empty($emails)) {
		foreach ($emails as $e) {
			if ($e['status'] == 0 || ($e['created'] + ((2 ** ($e['attempts'] - 1)) * 300) < time())) {
				process_queue_email($e);
			}
		}
	}
}

function process_queue_email($email) {
	if (read_setting('disable_email', 0)) {
		return true;
	}

	if (!isset($email['id'])) {
		return;
	}

	$from     = $email['from'];
	$fromname = $email['fromname'];
	$to       = $email['to'];
	$subject  = $email['subject'];
	$message  = $email['message'];

	$server = read_setting('smtp_server', '');

	if ($server != '') {
		$mail = new PHPMailer\PHPMailer(true);
		try {
			$mail->SMTPDebug = 4;
			$mail->isSMTP();
			$mail->Host        = $server;
			$mail->SMTPAuth    = true;
			$mail->Username    = read_setting('smtp_username', '');
			$mail->Password    = secured_decrypt(read_setting('smtp_password', ''));
			$mail->Port        = read_setting('smtp_port', 25);
			if (read_setting('smtp_security', '') != '') {
				$mail->SMTPSecure  = read_setting('smtp_security');
			}
			$mail->SMTPAutoTLS = true;

			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true
				)
			);

			$mail->setFrom($from, $fromname);
			$mail->addAddress($to);
			$mail->addReplyTo($from, $fromname);

			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));

			$status = $mail->send();
			log_email($to, $subject, $status);
			db_execute_prepare('DELETE FROM `mail_queue` WHERE `id` = ?', array($email['id']));
		} catch (Exception $e) {
			// Need to create a log in the DB
			$error = str_replace(' https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting', '', strip_tags($e->errorMessage())) . ' (Attempt ' . ($email['attempts'] + 1) . ')';
			log_email($to, $subject, $error);
			db_execute_prepare('UPDATE `mail_queue` SET `last_attempt` = ?, `status` = 1, `attempts` = ?, `error` = ? WHERE `id` = ?', array(time(), $email['attempts'] + 1, $error, $email['id']));
		}
	}
}

function log_email($to, $subject, $status) {
	if (is_array($to)) {
		$to = implode(', ', $to);
	}
	db_execute_prepare('INSERT INTO `log_emails` (`date`, `to`, `subject`, `status`) VALUES (?, ?, ?, ?)', array(time(), $to, $subject, $status));
}
