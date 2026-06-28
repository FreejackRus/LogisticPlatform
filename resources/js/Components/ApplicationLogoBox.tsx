import ApplicationLogo from './ApplicationLogo';

export default function ApplicationLogoBox(
    props: React.HTMLAttributes<HTMLDivElement>,
) {
    return (
        <div
            {...props}
            className="flex h-12 w-12 items-center justify-center rounded-md bg-[#111827]"
        >
            <ApplicationLogo className="h-12 w-12 fill-current text-[#fafafa]" />
        </div>
    );
}
