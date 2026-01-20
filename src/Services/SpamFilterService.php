<?php

namespace daacreators\CreatorsTicketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use daacreators\CreatorsTicketing\Models\SpamFilter;
use daacreators\CreatorsTicketing\Models\SpamLog;

class SpamFilterService
{
    protected array $filters;
    
    public function __construct()
    {
        // Cache filters for 10 minutes
        $this->filters = Cache::remember('spam_filters', 600, function () {
            return SpamFilter::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->get()
                ->groupBy('type')
                ->toArray();
        });
    }
    
    public function checkTicket(array $data, $user = null): array
    {
        $email = $user?->email ?? $data['email'] ?? null;
        $ipAddress = request()->ip();
        
        // 1. Check allow lists first (highest priority)
        if ($this->isAllowed($email)) {
            return ['allowed' => true];
        }
        
        // 2. Check email blocks
        if ($result = $this->checkEmail($email)) {
            if ($result['action'] === 'block') {
                $this->logSpam($user, $result, $email, $ipAddress, $data);
                return $result;
            }
        }
        
        // 3. Check domain blocks
        if ($result = $this->checkDomain($email)) {
            if ($result['action'] === 'block') {
                $this->logSpam($user, $result, $email, $ipAddress, $data);
                return $result;
            }
        }
        
        // 4. Check IP blocks
        if ($result = $this->checkIpAddress($ipAddress)) {
            if ($result['action'] === 'block') {
                $this->logSpam($user, $result, $email, $ipAddress, $data);
                return $result;
            }
        }
        
        // 5. Check rate limiting
        if ($result = $this->checkRateLimit($user, $email, $ipAddress)) {
            $this->logSpam($user, $result, $email, $ipAddress, $data);
            return $result;
        }
        
        // 6. Check content (keywords and patterns)
        if ($result = $this->checkContent($data)) {
            if ($result['action'] === 'block') {
                $this->logSpam($user, $result, $email, $ipAddress, $data);
                return $result;
            }
        }
        
        return ['allowed' => true];
    }
    
    protected function isAllowed(string $email): bool
    {
        if (!isset($this->filters['email'])) {
            return false;
        }
        
        foreach ($this->filters['email'] as $filter) {
            if ($filter['action'] === 'allow') {
                $values = is_array($filter['values']) ? $filter['values'] : [$filter['values']];
                
                foreach ($values as $value) {
                    if ($this->matchesEmail($email, $value, $filter['case_sensitive'])) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    protected function checkEmail(?string $email): ?array
    {
        if (!$email || !isset($this->filters['email'])) {
            return null;
        }
        
        foreach ($this->filters['email'] as $filter) {
            if ($filter['action'] !== 'allow') {
                $values = is_array($filter['values']) ? $filter['values'] : [$filter['values']];
                
                foreach ($values as $value) {
                    if ($this->matchesEmail($email, $value, $filter['case_sensitive'])) {
                        $this->incrementFilterHit($filter['id']);
                        return [
                            'allowed' => false,
                            'action' => $filter['action'],
                            'reason' => $filter['reason'] ?? 'Email address is blocked',
                            'filter_id' => $filter['id'],
                            'filter_type' => 'email',
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    protected function checkDomain(?string $email): ?array
    {
        // Domain checking removed - not useful for helpdesk systems
        return null;
    }
    
    protected function checkIpAddress(string $ip): ?array
    {
        if (!isset($this->filters['ip'])) {
            return null;
        }
        
        foreach ($this->filters['ip'] as $filter) {
            $values = is_array($filter['values']) ? $filter['values'] : [$filter['values']];
            
            foreach ($values as $value) {
                if ($this->matchesIp($ip, $value)) {
                    $this->incrementFilterHit($filter['id']);
                    return [
                        'allowed' => false,
                        'action' => $filter['action'],
                        'reason' => $filter['reason'] ?? 'IP address is blocked',
                        'filter_id' => $filter['id'],
                        'filter_type' => 'ip',
                    ];
                }
            }
        }
        
        return null;
    }
    
    protected function checkRateLimit($user, ?string $email, string $ip): ?array
    {
        if (!config('creators-ticketing.spam_protection.rate_limiting.enabled')) {
            return null;
        }
        
        $key = $user ? "ticket-user:{$user->id}" : "ticket-email:{$email}:{$ip}";
        $maxPerHour = config('creators-ticketing.spam_protection.rate_limiting.max_tickets_per_hour', 5);
        
        if (RateLimiter::tooManyAttempts($key, $maxPerHour)) {
            $seconds = RateLimiter::availableIn($key);
            return [
                'allowed' => false,
                'action' => 'block',
                'reason' => "Too many tickets submitted. Please try again in " . ceil($seconds / 60) . " minutes.",
                'filter_type' => 'rate_limit',
            ];
        }
        
        RateLimiter::hit($key, 3600); // 1 hour
        
        return null;
    }
    
    protected function checkContent(array $data): ?array
    {
        if (!config('creators-ticketing.spam_protection.content_filtering.enabled')) {
            return null;
        }
        
        $content = $this->extractContent($data);
        
        // Check keywords
        if (isset($this->filters['keyword'])) {
            foreach ($this->filters['keyword'] as $filter) {
                $values = is_array($filter['values']) ? $filter['values'] : [$filter['values']];
                
                foreach ($values as $keyword) {
                    if ($this->matchesKeyword($content, $keyword, $filter['case_sensitive'])) {
                        $this->incrementFilterHit($filter['id']);
                        return [
                            'allowed' => false,
                            'action' => $filter['action'],
                            'reason' => $filter['reason'] ?? 'Content contains blocked keywords',
                            'filter_id' => $filter['id'],
                            'filter_type' => 'keyword',
                            'matched_value' => $keyword,
                        ];
                    }
                }
            }
        }
        
        // Check patterns (regex)
        if (isset($this->filters['pattern'])) {
            foreach ($this->filters['pattern'] as $filter) {
                $values = is_array($filter['values']) ? $filter['values'] : [$filter['values']];
                
                foreach ($values as $pattern) {
                    if ($this->matchesPattern($content, $pattern)) {
                        $this->incrementFilterHit($filter['id']);
                        return [
                            'allowed' => false,
                            'action' => $filter['action'],
                            'reason' => $filter['reason'] ?? 'Content matches blocked pattern',
                            'filter_id' => $filter['id'],
                            'filter_type' => 'pattern',
                        ];
                    }
                }
            }
        }
        
        // Check excessive links
        if (config('creators-ticketing.spam_protection.content_filtering.check_links')) {
            $linkCount = $this->countLinks($content);
            $maxLinks = config('creators-ticketing.spam_protection.content_filtering.max_links_allowed', 3);
            
            if ($linkCount > $maxLinks) {
                return [
                    'allowed' => false,
                    'action' => 'block',
                    'reason' => "Too many links detected ({$linkCount} links)",
                    'filter_type' => 'content',
                ];
            }
        }
        
        return null;
    }
    
    protected function matchesEmail(string $email, string $value, bool $caseSensitive): bool
    {
        if ($caseSensitive) {
            return $email === $value || fnmatch($value, $email);
        }
        
        return strcasecmp($email, $value) === 0 || fnmatch(strtolower($value), strtolower($email));
    }
    
    protected function matchesIp(string $ip, string $value): bool
    {
        // Support CIDR notation
        if (str_contains($value, '/')) {
            return $this->ipInRange($ip, $value);
        }
        
        // Support wildcards
        return fnmatch($value, $ip);
    }
    
    protected function matchesKeyword(string $content, string $keyword, bool $caseSensitive): bool
    {
        if ($caseSensitive) {
            return str_contains($content, $keyword);
        }
        
        return str_contains(strtolower($content), strtolower($keyword));
    }
    
    protected function matchesPattern(string $content, string $pattern): bool
    {
        try {
            return (bool) preg_match($pattern, $content);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    protected function extractContent(array $data): string
    {
        $content = '';
        
        // Handle both direct custom_fields array and nested structure
        $fields = $data['custom_fields'] ?? $data;
        
        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                // Skip internal spam fields
                if (str_starts_with($key, '_spam_')) {
                    continue;
                }
                
                // Handle string values
                if (is_string($value)) {
                    $content .= ' ' . strip_tags($value);
                }
                // Handle array values (but skip file uploads)
                elseif (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_string($item) && !str_starts_with($item, 'livewire-file:')) {
                            $content .= ' ' . strip_tags($item);
                        }
                    }
                }
            }
        }
        
        return trim($content);
    }
    
    protected function countLinks(string $content): int
    {
        preg_match_all('/(https?:\/\/[^\s]+)/i', $content, $matches);
        return count($matches[0]);
    }
    
    protected function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $mask) = explode('/', $range);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    
    protected function incrementFilterHit(int $filterId): void
    {
        SpamFilter::where('id', $filterId)->increment('hits');
        SpamFilter::where('id', $filterId)->update(['last_triggered_at' => now()]);
    }
    
    protected function logSpam($user, array $result, ?string $email, string $ip, array $data): void
    {
        SpamLog::create([
            'user_id' => $user?->id,
            'spam_filter_id' => $result['filter_id'] ?? null,
            'email' => $email,
            'ip_address' => $ip,
            'filter_type' => $result['filter_type'],
            'action_taken' => 'blocked',
            'matched_value' => $result['matched_value'] ?? null,
            'ticket_data' => $data,
        ]);
    }
    
    public function clearCache(): void
    {
        Cache::forget('spam_filters');
    }
}