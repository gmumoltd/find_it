<?php
function cleanChatMessage($message, $isChatUnlocked) {
    // Regex pattern matching Kenyan phone formats (07..., 01..., 254..., +254...)
    $phonePattern = '/(\+?254|0)[17]\d{8}/'; 
    
    if (!$isChatUnlocked && preg_match($phonePattern, $message)) {
        return "[Contact details hidden until payment is settled]";
    }
    return $message;
}