<?php

namespace App\Enums;

enum DocumentType: string
{
    case DeliveryReceipt = 'delivery_receipt';
    case Invoice = 'invoice';
    case PurchaseOrderAttachment = 'po_attachment';
    case Quotation = 'quotation';
    case Contract = 'contract';
    case InspectionReport = 'inspection_report';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DeliveryReceipt => 'Delivery Receipt',
            self::Invoice => 'Invoice',
            self::PurchaseOrderAttachment => 'PO Attachment',
            self::Quotation => 'Quotation',
            self::Contract => 'Contract',
            self::InspectionReport => 'Inspection Report',
            self::Other => 'Other',
        };
    }

    /**
     * Short prefix used when generating document numbers.
     */
    public function numberPrefix(): string
    {
        return match ($this) {
            self::DeliveryReceipt => 'DR',
            self::Invoice => 'INV',
            self::PurchaseOrderAttachment => 'POA',
            self::Quotation => 'QTN',
            self::Contract => 'CTR',
            self::InspectionReport => 'IR',
            self::Other => 'DOC',
        };
    }
}
