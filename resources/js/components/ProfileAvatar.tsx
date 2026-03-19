import { useState } from 'react';
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar";
import { RadioGroup } from "@headlessui/react";
import { Button } from "@/components/ui/button";

const avatars = [
  '/avatars/boy-01.png',
  '/avatars/boy-02.png',
  '/avatars/boy-03.png',
  '/avatars/boy-04.png',
  '/avatars/girl-01.png',
  '/avatars/girl-02.png',
  '/avatars/girl-03.png',
  '/avatars/girl-04.png',
];

export default function ProfileAvatar({ auth }: { auth: any }) {
  const [selectedAvatar, setSelectedAvatar] = useState<string>(auth.user.avatar || avatars[0]);

  const saveAvatar = () => {
    // PATCH request to update user avatar
    fetch(route('profile.update-avatar'), {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')!,
      },
      body: JSON.stringify({ avatar: selectedAvatar }),
    }).then(res => {
      if (res.ok) {
        alert('Avatar updated!');
      }
    });
  };

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-medium">Choose your avatar</h3>

      <RadioGroup value={selectedAvatar} onChange={setSelectedAvatar} className="grid grid-cols-4 gap-4">
        {avatars.map((avatar) => (
          <RadioGroup.Option key={avatar} value={avatar} className="cursor-pointer">
            {({ checked }) => (
              <div
                className={`p-1 rounded-full border-2 ${checked ? 'border-blue-500' : 'border-transparent'}`}
              >
                <Avatar className="w-12 h-12">
                  <AvatarImage src={avatar} />
                  <AvatarFallback>U</AvatarFallback>
                </Avatar>
              </div>
            )}
          </RadioGroup.Option>
        ))}
      </RadioGroup>

      <Button onClick={saveAvatar}>Save Avatar</Button>
    </div>
  );
}