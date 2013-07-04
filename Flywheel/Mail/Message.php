<?php
namespace Flywheel\Mail;

include_once __DIR__ .'/../../PHPMailer/class.phpmailer.php';
class Message extends \PHPMailer {
    private $_serverConfig = array(
        'IsSmtp' => true,
        'IsHtml' => true,
        'Host' => null,
        'Port' => 25,
        'Username' => null,
        'Password' => null,
        'From' => null,
        'FromAddress' => null,
        'auto_clear' => true,
    );

    public function setConfig($config) {
        $this->_serverConfig = array_merge($this->_serverConfig, $config);
    }

    public function init($config = null) {
        if (!empty($config)) {
            $this->setConfig($config);
        }

        $config = $this->_serverConfig;

        if ($config['IsSmtp']) {
            $this->IsSMTP();
            unset ($config['IsSmtp']);
        }

        if ($config['IsHtml']) {
            $this->IsHTML(true);
            unset($config['IsHtml']);
        }
        $this->SetFrom($config['FromAddress'], $config['From']);
        unset($config['FromAddress']);
        unset($config['From']);

        foreach ($config as $setting => $value) {
            if (property_exists($this, $setting)) {
                $this->$setting = $value;
            }
        }
    }

    public function Send() {
        $status = parent::Send();
        if ($this->_serverConfig['auto_clear']) {
            $this->ClearAllRecipients();
        }

        return $status;
    }
}
