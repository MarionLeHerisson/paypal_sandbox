<?php

namespace App\Entity;

class Order
{
    public const STATUS_FRAUD_SUSPECTED = 'fraud_suspected';
    public const STATUS_PAID = 'paid';

    private int $id;
    
    private string $status;
    
    private int $amount;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }
}