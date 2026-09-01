import { LogOut, Settings } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Link } from 'react-router-dom';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useAuthStore } from '@/store/auth';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const navigate = useNavigate();
    const logout = useAuthStore((state) => state.logout);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link className="block w-full cursor-pointer" to="/settings/profile">
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                className="cursor-pointer"
                data-test="logout-button"
                onSelect={(event) => {
                    event.preventDefault();
                    void handleLogout();
                }}
            >
                <LogOut className="mr-2" />
                Log out
            </DropdownMenuItem>
        </>
    );
}
