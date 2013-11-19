<?php
namespace Flywheel\Mail;
class Sender {
    /**
     * @var \Flywheel\Mail\Message;
     */
    protected static $_mailer;

    public static function getMailer() {
        if (null == self::$_mailer) {
            $config = self::loadConfig();
            shuffle($config);
            $config[0]['exception'] = (boolean) $config[0]['exception'];
            self::$_mailer = new Message($config[0]['exception']);
            self::$_mailer->init($config[0]);
        }
        return self::$_mailer;
    }

    public static function loadConfig() {
        $config = \Flywheel\Config\ConfigHandler::load('global.config.mailsvrs', 'mail');
        return $config;
    }

    /**
     * Send an email to recipient(s)
     *
     * @param array|string $recipients
     *  exp: [
     *      'abc@xyz.com',
     *      ['abc@xyz.com,'abc name']
     *      ];
     * @param string $subject
     * @param string $message
     * @return bool
     * @throws \phpmailerException
     */
    public static function sendMail($recipients, $subject, $message) {
        $recipients = (array) $recipients;
        if (empty($recipients)) {
            return false;
        }

        $mailer = self::getMailer();
        try {
            $mailer->Subject = $subject;
            $mailer->MsgHTML($message);
            foreach ($recipients as $recipient) {
                if (is_array($recipient)) {
                    $mailer->AddAddress($recipient[0], $recipient[1]);
                } else {
                    $mailer->AddAddress($recipient);
                }
            }

            return $mailer->Send();
        } catch(\phpmailerException $pme) {
            throw $pme;
        }
    }
}
