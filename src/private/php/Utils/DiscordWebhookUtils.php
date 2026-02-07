<?php

namespace Wruczek\TSWebsite\Utils;

use Wruczek\TSWebsite\Config;

class DiscordWebhookUtils {

	private function __construct() {}

	public static function sendLoginVerification(array $clientInfo, string $ipAddress, string $userCode): void {
		$webhookUrl = (string) Config::get("discord_webhook_url");
		if (!$webhookUrl) {
			return;
		}

		$clientNickname = isset($clientInfo["client_nickname"]) ? (string) $clientInfo["client_nickname"] : "Unknown";
		$clientUniqueId = isset($clientInfo["client_unique_identifier"]) ? (string) $clientInfo["client_unique_identifier"] : "Unknown";
		$clientDatabaseId = isset($clientInfo["client_database_id"]) ? (string) $clientInfo["client_database_id"] : "Unknown";
		$clientCountry = isset($clientInfo["client_country"]) ? strtoupper((string) $clientInfo["client_country"]) : "";
		$clientPlatform = isset($clientInfo["client_platform"]) ? (string) $clientInfo["client_platform"] : null;
		$clientVersion = isset($clientInfo["client_version"]) ? (string) $clientInfo["client_version"] : null;

		$countryEmoji = $clientCountry ? ":flag_" . strtolower($clientCountry) . ":" : "";
		$clientLine = $clientPlatform && $clientVersion ? ($clientPlatform . " (" . $clientVersion . ")") : ($clientPlatform ?: "Unknown");

		$fields = [
			[
				"name" => "User",
				"value" => "`" . $clientNickname . "`",
				"inline" => true
			],
			[
				"name" => "IP",
				"value" => "`" . $ipAddress . "`",
				"inline" => true
			],
			[
				"name" => "Country",
				"value" => ($countryEmoji ? $countryEmoji . " " : "") . ($clientCountry ?: "Unknown"),
				"inline" => true
			],
			[
				"name" => "Client",
				"value" => $clientLine,
				"inline" => true
			],
			[
				"name" => "Unique ID",
				"value" => "`" . $clientUniqueId . "`",
				"inline" => true
			],
			[
				"name" => "DBID",
				"value" => "`" . $clientDatabaseId . "`",
				"inline" => true
			],
			[
				"name" => "Verification Code",
				"value" => "`" . $userCode . "`",
				"inline" => true
			]
		];

		$embed = [
			"title" => "Login verification successful",
			"color" => 5763719,
			"fields" => $fields,
			"timestamp" => gmdate("c")
		];

		self::postWebhook($webhookUrl, [
			"embeds" => [$embed]
		]);
	}

	private static function postWebhook(string $webhookUrl, array $payload): void {
		$ch = \curl_init($webhookUrl);
		\curl_setopt($ch, CURLOPT_POST, true);
		\curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		\curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		\curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		\curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		\curl_exec($ch);
		\curl_close($ch);
	}
}

