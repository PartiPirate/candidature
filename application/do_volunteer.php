<?php
/*
	Copyright 2015-2019 Cédric Levieux, Parti Pirate

	This file is part of Recrutement.

    Recrutement is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Recrutement is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Recrutement.  If not, see <http://www.gnu.org/licenses/>.
*/
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

function startsWith($haystack, $needle) {
	// search backwards starting from haystack length characters from the end
	return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

function endsWith($haystack, $needle) {
	// search forward starting from end minus needle length characters
	return $needle === "" || strpos($haystack, $needle, strlen($haystack) - strlen($needle)) !== FALSE;
}

include_once("config/database.php");
include_once("config/mail.php");
include_once("language/language.php");
require_once("engine/bo/AddressBo.php");
require_once("engine/bo/DocumentBo.php");
require_once("engine/bo/CandidateAnswerBo.php");
require_once("engine/bo/UserBo.php");

$connection = openConnection();
$addressBo = AddressBo::newInstance($connection);
$documentBo = DocumentBo::newInstance($connection);
$candidateAnswerBo = CandidateAnswerBo::newInstance($connection, $config);

$data = array();

if (!count($_REQUEST)) {
	$data["ko"] = "ko";
	echo json_encode($data);
	exit();
}

if ($_REQUEST["email"]) {
	exit();
}

$email = $_REQUEST["xxx"];
$confirmationMail = $_REQUEST["confirmationMail"];

if ($confirmationMail != $email) {
	$data["ko"] = "ko";
}
else {
	$address = array();
	$firstname = $_REQUEST["firstname"];
	$lastname = $_REQUEST["lastname"];

	$address["add_entity"] = $firstname . " " . $lastname;
	$address["add_line_1"] = $_REQUEST["line1"];
	$address["add_line_2"] = $_REQUEST["line2"];
	$address["add_zip_code"] = $_REQUEST["zipCode"];
	$address["add_city"] = $_REQUEST["city"];
	$address["add_company_name"] = "";
	$address["add_country_id"] = 1;

	$addressBo->addAddress($address);

	$data["address_id"] = $address["add_id"];

	$candidature = array();
	$candidature["can_bodyshot_id"] = null;

	$basePath = $_SERVER["SCRIPT_FILENAME"];
	$basePath = substr($basePath, 0, strrpos($basePath, "/") + 1);
	
	$documentPath = $config["document_directory"];
	if (!endsWith($documentPath, "/")) {
		$documentPath .= "/";
	}

	if (isset($_FILES["bodyshotFile"])) {
		$file = $_FILES["bodyshotFile"];

		if ($file["name"]) {
			$document = array();

			$data['files']["bodyshotFile"]['src'] = $file["name"];

			$filename = time() . rand(0, time());
			$computeFilename = UserBo::computePassword($filename);
			
			move_uploaded_file($file["tmp_name"], $basePath . $documentPath . $computeFilename);

			$document = array();
			$document["doc_task_id"] = null;
			$document["doc_campaign_id"] = null;
			$document["doc_name"] = $file["name"];
			$document["doc_size"] = $file["size"];
			$document["doc_mime_type"] = $file["type"];
			$document["doc_label"] = "bodyshot";
			$document["doc_path"] = $documentPath . $computeFilename;
			$documentBo->addDocument($document);

			$data['files']["bodyshotFile"]['id'] = $document["doc_id"];
			$candidature["can_bodyshot_id"] = $document["doc_id"];
		}
	}

	$positions = explode(",", $_REQUEST["candidateInput"]);
//	$positions = json_encode($positions);

	$circonscriptions = explode(",", $_REQUEST["circonscriptions"]);
//	$circonscriptions = json_encode($circonscriptions);

	$candidature["can_address_id"] = $address["add_id"];
	$candidature["can_sex"] = $_REQUEST["sexInput"];
	$candidature["can_firstname"] = $firstname;
	$candidature["can_lastname"] = $lastname;
	$candidature["can_telephone"] = $_REQUEST["telephone"];
	$candidature["can_mail"] = $email;
	$candidature["can_authorize"] = isset($_REQUEST["authorize"]) ? $_REQUEST["authorize"] : 0;

	$candidature["circonscriptions"] = $circonscriptions;
	$candidature["positions"] = $positions;

	$addressBo->addCandidature($candidature);

	$data["candidature_id"] = $candidature["can_id"];

	/* CODE SPECIFIQUE */
	$answer = array("cas_candidature_id" => $candidature["can_id"], "cas_question_id" => "24", "cas_answer" => $_REQUEST["faith"]);
	$candidateAnswerBo->save($answer);

	$answer = array("cas_candidature_id" => $candidature["can_id"], "cas_question_id" => "26", "cas_answer" => $_REQUEST["adherentInput"]);
	$candidateAnswerBo->save($answer);

	$answer = array("cas_candidature_id" => $candidature["can_id"], "cas_question_id" => "25", "cas_answer" => (($_REQUEST["listsInput"] == "true") ? ("Oui : " . $_REQUEST["listInput"]) : "Non"));
	$candidateAnswerBo->save($answer);

	$answer = array("cas_candidature_id" => $candidature["can_id"], "cas_question_id" => "27", "cas_answer" => (($_REQUEST["otherCityChoiceInput"] == "true") ? ($_REQUEST["otherCityInput"]) : $_REQUEST["add_city"]));
	$candidateAnswerBo->save($answer);

	// Send mail
	$mailMessage = "Bonjour

Vous avez manifesté votre volonté de participer aux élections municipales de 2020 sous l'étiquette du Parti Pirate.
Nous l'avons bien pris en compte et nous reviendrons vers vous très prochainement afin d'échanger au sujet de cette candidature.

Piratement,
L'équipe Élections";
	
	$subject = "Votre Candidature aux elections municipales de 2020 au nom du Parti Pirate";
	
	$mail = getMailInstance();
	
	$mail->setFrom($config["smtp"]["from.address"], $config["smtp"]["from.name"]);
	$mail->addReplyTo($config["smtp"]["from.address"], $config["smtp"]["from.name"]);
	
	$mail->Subject = subjectEncode($subject);
	$mail->msgHTML(str_replace("\n", "<br>\n", utf8_decode($mailMessage)));
	$mail->AltBody = utf8_decode($mailMessage);
	
	$mail->addAddress($email);
	
//	$mail->SMTPSecure = "ssl";
	if ($mail->send()) {
		$data["mail"] = true;
		//		echo "Send SN Mails<br/>";
	}

	$mail = getMailInstance();
	
	$mail->setFrom($config["smtp"]["from.address"], $config["smtp"]["from.name"]);
	$mail->addReplyTo($config["smtp"]["from.address"], $config["smtp"]["from.name"]);
	
	$subject = "[2020] Un-e nouvel-le candidat-e";
	$mailMessage = "La personne " . $candidature["can_lastname"] . " " . $candidature["can_firstname"] . " a fait acte de candidature.

Informations complémentaires :
------------------------------

Circonscriptions : " . $_REQUEST["circonscriptions"] . "
Positions : ";

$positionSeparator = "";
	
foreach($positions as $position) {
	$mailMessage .= $positionSeparator;
	$mailMessage .= lang("candidate_position_" . $position);
	
	$positionSeparator = ", ";
}
	
	$mailMessage .= "

Merci de vous en occuper.
			
Un bot qui distribue des tâches";
	
	$mail->Subject = subjectEncode($subject);
	$mail->msgHTML(str_replace("\n", "<br>\n", utf8_decode($mailMessage)));
	$mail->AltBody = utf8_decode($mailMessage);
	
	$mail->addAddress("municipales@mail.partipirate.org", "Equipe Elections");

//	$mail->SMTPSecure = "ssl";
	if ($mail->send()) {
		//		echo "Send SN Mails<br/>";
		$data["sent"] = "sent";
	}
	
	
	$data["ok"] = "ok";
}

echo json_encode($data);
?>