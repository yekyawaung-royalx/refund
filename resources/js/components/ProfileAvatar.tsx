import { useState } from "react";
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar";
import { RadioGroup } from "@headlessui/react";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";
import HeadingSmall from "./heading-small";
import { toast } from "sonner";

const avatars = [
  "boy-01.png",
  "boy-02.png",
  "boy-03.png",
  "boy-04.png",
  "boy-05.png",
  "boy-06.png",
  "boy-07.png",
  "boy-08.png",
  "boy-09.png",
  "girl-01.png",
  "girl-02.png",
  "girl-03.png",
  "girl-04.png",
  "girl-05.png",
  "girl-06.png",
  "girl-07.png",
  "girl-08.png",
  "girl-09.png",
];

export default function ProfileAvatar({ auth }: { auth: any }) {
  const [selectedAvatar, setSelectedAvatar] = useState(
    auth.user.profile || "girl-01.png"
  );

  const [loading, setLoading] = useState(false);

  const saveAvatar = () => {
  setLoading(true);

  const toastId = toast.loading("Updating avatar...");

  router.patch(
    route("profile.update-avatar"),
    { profile: selectedAvatar },
    {
      preserveScroll: true,

      onSuccess: () => {
        toast.success("Avatar updated successfully!", {
          id: toastId,
        });
      },

      onError: () => {
        toast.error("Failed to update avatar", {
          id: toastId,
        });
      },

      onFinish: () => setLoading(false),
    }
  );
};

  return (
    <div className="space-y-4">
      <HeadingSmall title="Avatar" description="Change your avatar" />

      <RadioGroup
  value={selectedAvatar}
  onChange={setSelectedAvatar}
  className="grid grid-cols-6 gap-4"
>
  {avatars.map((avatar) => (
    <RadioGroup.Option key={avatar} value={avatar} className="flex justify-start">
      {({ checked }) => (
        <div
          className={`
            p-1 rounded-full border-2 transition
            ${checked ? "border-green-500" : "border-transparent hover:border-muted"}
          `}
        >
          <Avatar className="w-14 h-14 rounded-full overflow-hidden">
            <AvatarImage
              src={`/avatars/${avatar}`}
              className="object-cover w-full h-full"
            />
            <AvatarFallback>U</AvatarFallback>
          </Avatar>
        </div>
      )}
    </RadioGroup.Option>
  ))}
</RadioGroup>

      <Button className="bg-green-500 hover:bg-green-600 text-white" onClick={saveAvatar} disabled={loading}>
        {loading ? "Saving..." : "Save Avatar"}
      </Button>
    </div>
  );
}