export default function Heading({ title, description }: { title: string; description?: string }) {
  return (
    <div className="flex min-w-0 flex-col gap-1">
      <h2 className="text-xl leading-tight font-semibold tracking-tight">{title}</h2>
      {description && <p className="text-muted-foreground max-w-3xl text-sm leading-relaxed">{description}</p>}
    </div>
  );
}
