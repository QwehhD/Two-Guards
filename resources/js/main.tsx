import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from '@/App';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

const container = document.getElementById('root');

if (!container) {
    throw new Error('Root element #root not found');
}

createRoot(container).render(
    <StrictMode>
        <BrowserRouter>
            <TooltipProvider delayDuration={0}>
                <App />
                <Toaster />
            </TooltipProvider>
        </BrowserRouter>
    </StrictMode>,
);

// This will set light / dark mode on load...
initializeTheme();
