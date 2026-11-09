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

/*
	Sends what is waiting in the queue.  A scheduled report is queued once per recipient and
	can be megabytes, so the queue is listed without the bodies, and each body is loaded only
	while that one message is sent.
*/
function process_mail_queue($newonly = false) {
	$cols = '`id`, `to`, `subject`, `status`, `attempts`, `last_attempt`, `error`';
	if ($newonly) {
		$emails = db_fetch_assocs("SELECT $cols FROM `mail_queue` WHERE `status` = 0 ORDER BY `id`");
	} else {
		$emails = db_fetch_assocs("SELECT $cols FROM `mail_queue` ORDER BY `id`");
	}

	if (!empty($emails)) {
		foreach ($emails as $e) {
			// Back off from the last attempt: 5 minutes, 10, 20 ... and give up after 10 tries
			if ($e['attempts'] >= 10) {
				log_email($e['to'], $e['subject'], 'Gave up after ' . $e['attempts'] . ' attempts: ' . $e['error']);
				db_execute_prepare('DELETE FROM `mail_queue` WHERE `id` = ?', array($e['id']));
			} elseif ($e['status'] == 0 || ($e['last_attempt'] + ((2 ** max(0, $e['attempts'] - 1)) * 300) < time())) {
				$full = db_fetch_assoc_prepare('SELECT * FROM `mail_queue` WHERE `id` = ?', array($e['id']));
				if (isset($full['id'])) {
					process_queue_email($full);
				}
				unset($full);
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
			// Debug output would include the AUTH exchange, password and all
			$mail->SMTPDebug = 0;
			$mail->isSMTP();
			$mail->Host        = $server;
			$username          = read_setting('smtp_username', '');
			$mail->SMTPAuth    = ($username != '');
			$mail->Username    = $username;
			$mail->Password    = (string) secured_decrypt(read_setting('smtp_password', ''));
			$mail->Port        = read_setting('smtp_port', 25);
			if (read_setting('smtp_security', '') != '') {
				$mail->SMTPSecure  = read_setting('smtp_security');
			}
			$mail->SMTPAutoTLS = true;

			// Certificates are checked against the container's CA bundle unless turned off for a self-signed server
			if (read_setting('smtp_verify_cert', '1') != '1') {
				$mail->SMTPOptions = array(
					'ssl' => array(
						'verify_peer'       => false,
						'verify_peer_name'  => false,
						'allow_self_signed' => true
					)
				);
			}

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
			$error = str_replace(' https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting', '', strip_tags($e->getMessage())) . ' (Attempt ' . ($email['attempts'] + 1) . ')';
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
