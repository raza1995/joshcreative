<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'subject',
        'body',
        'status',
        'shopify_order_id',
        'sent_at',
        'auto_sent',
        'ai_confidence',
        'ai_decision_reason',
        'tone_check',
        'content_check',
        'risk_assessment',
        'policy_compliance',
        'original_email'
    ];

    public function shopifyOrder()
    {
        return $this->belongsTo(ShopifyOrder::class, 'shopify_order_id', 'order_number');
    }

    public function review()
    {
        return $this->hasOne(EmailReview::class);
    }
}
