<?php

namespace BitDreamIT\MikoPBX\Services;

/**
 * IVR Builder Service
 *
 * Fluent builder for creating interactive IVR menus
 * programmatically from Laravel.
 *
 * Usage:
 *   $ivr = IVRBuilder::make('Main Menu')
 *       ->greeting('welcome.wav')
 *       ->timeout(10)
 *       ->onPress(1, 'transfer', '101')
 *       ->onPress(2, 'transfer', '102')
 *       ->onPress(0, 'transfer', '200')
 *       ->onTimeout('repeat')
 *       ->onInvalid('repeat', 3)
 *       ->build();
 */
class IVRBuilder
{
    private string $name;
    private string $greetingFile   = '';
    private int    $timeout        = 10;
    private int    $maxInvalid     = 3;
    private array  $keypresses     = [];
    private string $timeoutAction  = 'repeat';
    private string $invalidAction  = 'repeat';
    private array  $questions      = [];

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    // ─────────────────────────────────────────
    // FLUENT BUILDER
    // ─────────────────────────────────────────

    public function greeting(string $audioFile): static
    {
        $this->greetingFile = $audioFile;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function maxInvalidAttempts(int $times): static
    {
        $this->maxInvalid = $times;
        return $this;
    }

    /**
     * Key press actions:
     *   transfer  → transfer to extension
     *   queue     → send to call queue
     *   playback  → play audio file
     *   hangup    → hang up call
     *   ivr       → go to another IVR menu
     *   voicemail → send to voicemail
     */
    public function onPress(int|string $key, string $action, string $value = ''): static
    {
        $this->keypresses[(string)$key] = [
            'key'    => (string)$key,
            'action' => $action,
            'value'  => $value,
        ];
        return $this;
    }

    public function onTimeout(string $action, string $value = ''): static
    {
        $this->timeoutAction = $action;
        return $this;
    }

    public function onInvalid(string $action, string $value = ''): static
    {
        $this->invalidAction = $action;
        return $this;
    }

    // Shortcut methods
    public function pressToTransfer(int|string $key, string $extension): static
    {
        return $this->onPress($key, 'transfer', $extension);
    }

    public function pressToQueue(int|string $key, string $queueNumber): static
    {
        return $this->onPress($key, 'queue', $queueNumber);
    }

    public function pressToVoicemail(int|string $key): static
    {
        return $this->onPress($key, 'voicemail', '');
    }

    public function pressToHangup(int|string $key): static
    {
        return $this->onPress($key, 'hangup', '');
    }

    // ─────────────────────────────────────────
    // BUILD
    // ─────────────────────────────────────────

    public function build(): array
    {
        return [
            'name'      => $this->name,
            'questions' => [
                [
                    'questionId'   => '1',
                    'questionText' => $this->greetingFile,
                    'timeout'      => $this->timeout,
                    'maxInvalid'   => $this->maxInvalid,
                    'press'        => array_values($this->keypresses),
                    'onTimeout'    => $this->timeoutAction,
                    'onInvalid'    => $this->invalidAction,
                ]
            ],
        ];
    }

    /** Multi-level IVR — add a question/level */
    public function addLevel(string $questionId, string $audioFile, array $keypresses): static
    {
        $this->questions[] = [
            'questionId'   => $questionId,
            'questionText' => $audioFile,
            'press'        => $keypresses,
        ];
        return $this;
    }

    /** Build multi-level IVR */
    public function buildMultiLevel(): array
    {
        return [
            'name'      => $this->name,
            'questions' => $this->questions,
        ];
    }

    // ─────────────────────────────────────────
    // PRESET TEMPLATES
    // ─────────────────────────────────────────

    /** Simple Sales / Support IVR */
    public static function salesSupportTemplate(
        string $salesExt,
        string $supportExt,
        string $managerExt
    ): array {
        return static::make('Main IVR')
            ->pressToTransfer(1, $salesExt)
            ->pressToTransfer(2, $supportExt)
            ->pressToTransfer(0, $managerExt)
            ->pressToVoicemail(9)
            ->onTimeout('repeat')
            ->onInvalid('repeat')
            ->build();
    }

    /** Survey IVR — collect customer ratings */
    public static function surveyTemplate(string $afterSalesExt): array
    {
        return static::make('CSAT Survey')
            ->greeting('survey_intro.wav')
            ->timeout(15)
            ->onPress(1, 'playback', 'survey_thank_excellent.wav')
            ->onPress(2, 'playback', 'survey_thank_good.wav')
            ->onPress(3, 'playback', 'survey_thank_poor.wav')
            ->onPress(0, 'transfer', $afterSalesExt)
            ->build();
    }
}
