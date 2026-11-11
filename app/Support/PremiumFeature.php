<?php

namespace App\Support;

/**
 * What only Premium accounts get. Everything else is free: unlimited pigeons and pairings, printable pedigrees
 * (without a QR code or contact details), Lineage, Locations, OLR, Clubs and Sales & Auctions.
 *
 * resources/js/lib/premium.ts lists the same features for the billing page and the "Premium" badges.
 */
enum PremiumFeature: string
{
    case PairingAnalysis = 'pairing-analysis';
    case LoftCheck = 'loft-check';
    case PedigreeSharing = 'pedigree-sharing';
    case PedigreeExport = 'pedigree-export';
    case PedigreeContact = 'pedigree-contact';
    case PedigreeCopy = 'pedigree-copy';

    public function title(): string
    {
        return match ($this) {
            self::PairingAnalysis => 'Pairing Analysis and breeding plan',
            self::LoftCheck => 'Loft Check',
            self::PedigreeSharing => 'Online pedigrees with a QR code',
            self::PedigreeExport => 'Save pedigrees as an image or PDF',
            self::PedigreeContact => 'Your contact details on pedigrees',
            self::PedigreeCopy => 'Copy a pedigree from another bird',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PairingAnalysis => 'See the inbreeding (COI) of a pair before you mate them, their common ancestors and how much each one adds, and save pairs for next season. Pedigrees also show the bird\'s COI.',
            self::LoftCheck => 'Count your birds after a training toss, find the missing ones fast, and keep a history of every count.',
            self::PedigreeSharing => 'Each pedigree gets a QR code and its own page online. Make it public or protect it with a password that expires.',
            self::PedigreeExport => 'Download a pedigree as a sharp image or PDF to send to buyers and post online.',
            self::PedigreeContact => 'Print your phone number, email and address on every pedigree so buyers can reach you.',
            self::PedigreeCopy => 'Fill a pedigree in seconds by copying the ancestors you already entered on a brother, sister or parent.',
        };
    }

    /**
     * @return array{key: string, title: string, description: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->value, 'title' => $this->title(), 'description' => $this->description()];
    }
}
