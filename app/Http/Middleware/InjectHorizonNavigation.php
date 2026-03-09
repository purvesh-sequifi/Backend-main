<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * InjectHorizonNavigation Middleware
 * 
 * Injects custom navigation bar into Horizon dashboard HTML responses
 */
class InjectHorizonNavigation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only inject for HTML responses on Horizon routes
        if ($request->is('horizon*') && 
            $response instanceof \Illuminate\Http\Response &&
            str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            
            $content = $response->getContent();
            
            // Inject navigation before </body> tag
            if (str_contains($content, '</body>')) {
                $navigation = $this->getNavigationHtml();
                $content = str_replace('</body>', $navigation . '</body>', $content);
                $response->setContent($content);
            }
        }
        
        return $response;
    }
    
    private function getNavigationHtml(): string
    {
        $user = Auth::guard('feature-flags')->user();
        
        // Safely extract user properties with fallback values
        $firstName = $user?->first_name ?? 'Admin';
        $lastName = $user?->last_name ?? 'User';
        $userName = $user ? $firstName . ' ' . $lastName : 'Admin User';
        $userInitials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        
        // Escape all user-controlled and dynamic values for safe HTML embedding
        $userName = e($userName);
        $userInitials = e($userInitials);
        $logoutUrl = e(route('admin.logout'));
        $adminUrl = e(route('admin.dashboard'));
        $featureFlagsUrl = e(route('feature-flags.index'));
        $csrfToken = e(csrf_token());
        
        return <<<HTML
<script>
(function() {
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addCustomNav);
    } else {
        addCustomNav();
    }
    
    function addCustomNav() {
        // Check if nav already exists
        if (document.getElementById('sequifi-custom-nav')) return;
        
        // Create navigation bar
        const nav = document.createElement('div');
        nav.id = 'sequifi-custom-nav';
        nav.innerHTML = `
            <style>
                #sequifi-custom-nav {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    z-index: 99999;
                    background: rgba(255, 255, 255, 0.98);
                    backdrop-filter: blur(12px);
                    border-bottom: 1px solid #e2e8f0;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                }
                #sequifi-custom-nav-content {
                    max-width: 100%;
                    margin: 0 auto;
                    padding: 10px 24px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .sequifi-nav-brand {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .sequifi-nav-icon {
                    width: 36px;
                    height: 36px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.25);
                    flex-shrink: 0;
                }
                .sequifi-nav-title {
                    font-size: 15px;
                    font-weight: 700;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    line-height: 1.2;
                }
                .sequifi-nav-subtitle {
                    font-size: 11px;
                    color: #64748b;
                    font-weight: 500;
                }
                .sequifi-nav-links {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .sequifi-nav-link {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    padding: 7px 14px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 500;
                    transition: all 0.2s;
                    color: #64748b;
                    white-space: nowrap;
                }
                .sequifi-nav-link:hover {
                    background: #f1f5f9;
                    color: #334155;
                    transform: translateY(-1px);
                }
                .sequifi-nav-link.active {
                    background: #ede9fe;
                    color: #7c3aed;
                }
                .sequifi-nav-divider {
                    width: 1px;
                    height: 24px;
                    background: #e2e8f0;
                }
                .sequifi-nav-user {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .sequifi-nav-avatar {
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 700;
                    font-size: 12px;
                    flex-shrink: 0;
                }
                .sequifi-nav-username {
                    font-size: 13px;
                    font-weight: 500;
                    color: #334155;
                }
                .sequifi-nav-logout {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    padding: 7px 14px;
                    border-radius: 8px;
                    background: #ef4444;
                    color: white;
                    border: none;
                    font-size: 13px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .sequifi-nav-logout:hover {
                    background: #dc2626;
                    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
                    transform: translateY(-1px);
                }
                body {
                    padding-top: 57px !important;
                }
                
                /* Adjust Horizon's own header if it exists */
                .card {
                    margin-top: 0 !important;
                }
            </style>
            <div id="sequifi-custom-nav-content">
                <div class="sequifi-nav-brand">
                    <div class="sequifi-nav-icon">
                        <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="sequifi-nav-title">Sequifi Admin Portal</div>
                        <div class="sequifi-nav-subtitle">Queue Dashboard</div>
                    </div>
                </div>
                <div class="sequifi-nav-links">
                    <a href="{$adminUrl}" class="sequifi-nav-link">
                        <span>🏠</span> Admin Portal
                    </a>
                    <a href="{$featureFlagsUrl}" class="sequifi-nav-link">
                        <span>🎯</span> Feature Flags
                    </a>
                    <a href="/horizon" class="sequifi-nav-link active">
                        <span>📊</span> Queue Dashboard
                    </a>
                    <div class="sequifi-nav-divider"></div>
                    <div class="sequifi-nav-user">
                        <div class="sequifi-nav-avatar">{$userInitials}</div>
                        <span class="sequifi-nav-username">{$userName}</span>
                    </div>
                    <form method="POST" action="{$logoutUrl}" style="margin: 0;">
                        <input type="hidden" name="_token" value="{$csrfToken}">
                        <button type="submit" class="sequifi-nav-logout">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        `;
        
        // Insert at the beginning of body
        if (document.body) {
            document.body.insertBefore(nav, document.body.firstChild);
        }
    }
})();
</script>
HTML;
    }
}
