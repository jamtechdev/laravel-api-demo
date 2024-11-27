<?php

namespace App\Services;

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

class SmsService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(
            env('TWILIO_SID'),
            env('TWILIO_AUTH_TOKEN')
        );
    }

    // Send Verification Code (OTP)
    public function sendVerificationCode($phoneNumber)
    {
        try {
            $this->twilio->verify->v2->services(env('TWILIO_VERIFY_SERVICE_SID'))
                ->verifications
                ->create($this->format($phoneNumber), "sms"); // or "call"
            return true; // return true on success
        } catch (TwilioException $e) {
            // Log the error if needed: logger()->error($e->getMessage());
            return false; // return false on failure
        }
    }

    // Verify the OTP Code
    public function verifyCode($phoneNumber, $code)
    {
        try {
            $verificationCheck = $this->twilio->verify->v2->services(env('TWILIO_VERIFY_SERVICE_SID'))
                ->verificationChecks
                ->create([
                    'to' => $this->format($phoneNumber), // or $phoneNumber
                    'code' => $code,
                ]);

            return $verificationCheck->status === 'approved'; // return true if approved
        } catch (TwilioException $e) {
            // Log the error if needed: logger()->error($e->getMessage());
            return false; // return false on failure
        }
    }

    // Format the phone number
    public function format($number, $countryCode = '+91')
    {
        // Check if the number already contains the country code
        if (strpos($number, '+') === 0) {
            return $number; // Return the number as is
        }

        // Remove any non-numeric characters
        $number = preg_replace('/\D/', '', $number);

        // Add the country code based on the length of the number
        if (strlen($number) === 10) {
            return $countryCode . $number;
        }

        // Optionally, you can throw an exception or handle invalid lengths
        throw new \InvalidArgumentException('Invalid phone number length');
    }
}
