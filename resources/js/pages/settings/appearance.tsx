import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';

export default function Appearance() {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Appearance settings"
                description="Update the appearance settings for your account"
            />
            <AppearanceTabs />
        </div>
    );
}
