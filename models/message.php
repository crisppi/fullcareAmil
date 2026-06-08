<?php

class Message
{

    private $url;
    private $type;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function setMessage($msg, $type, $redirect = "index.php")
    {

        $_SESSION["msg"] = $msg;
        $_SESSION["type"] = $type;
        if (function_exists('fullcare_flash')) {
            fullcare_flash(strip_tags((string)$msg), (string)$type);
        }

        if ($redirect != "back") {
            $target = $this->url . ltrim((string)$redirect, '/');
            if (!headers_sent()) {
                header("Location: " . $target, true, 303);
            } else {
                $safeTarget = json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                echo "<script>window.location.href={$safeTarget};</script>";
            }
        } else {
            $target = $_SERVER["HTTP_REFERER"] ?? $this->url;
            if (!headers_sent()) {
                header("Location: " . $target, true, 303);
            } else {
                $safeTarget = json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                echo "<script>window.location.href={$safeTarget};</script>";
            }
        }
    }

    public function getMessage()
    {

        if (!empty($_SESSION["msg"])) {
            return [
                "msg" => $_SESSION["msg"],
                "type" => $_SESSION["type"]
            ];
        } else {
            return false;
        }
    }

    public function clearMessage()
    {
        $_SESSION["msg"] = "";
        $_SESSION["type"] = "";
    }
}
