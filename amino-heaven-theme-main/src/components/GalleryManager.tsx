import { useEffect, useRef, useState } from "react";
import { ImagePlus, Trash2, X } from "lucide-react";
import { Button } from "@/components/ui/button";

const MAX_SLOTS = 21;
const BATCH_LIMIT = 10;
const STORAGE_KEY = "amino-heaven-gallery";

type Slot = string | null;

function loadSlots(): Slot[] {
  if (typeof window === "undefined") return Array(MAX_SLOTS).fill(null);
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return Array(MAX_SLOTS).fill(null);
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return Array(MAX_SLOTS).fill(null);
    return Array.from({ length: MAX_SLOTS }, (_, i) => (typeof parsed[i] === "string" ? parsed[i] : null));
  } catch {
    return Array(MAX_SLOTS).fill(null);
  }
}

export function GalleryManager() {
  const [slots, setSlots] = useState<Slot[]>(Array(MAX_SLOTS).fill(null));
  const [hydrated, setHydrated] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setSlots(loadSlots());
    setHydrated(true);
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(slots));
    } catch {
      setNotice("Storage is full — images will only last for this session.");
    }
  }, [slots, hydrated]);

  const filled = slots.filter(Boolean).length;
  const remaining = MAX_SLOTS - filled;

  const handleFiles = (files: FileList | null) => {
    if (!files || files.length === 0) return;
    const images = Array.from(files).filter((file) => file.type.startsWith("image/"));
    if (images.length === 0) {
      setNotice("Only image files can be added.");
      return;
    }
    let batch = images;
    if (images.length > BATCH_LIMIT) {
      batch = images.slice(0, BATCH_LIMIT);
      setNotice(`Only the first ${BATCH_LIMIT} images were added — upload the rest in the next batch.`);
    } else {
      setNotice(null);
    }
    setSlots((current) => {
      const next = [...current];
      let added = 0;
      for (const file of batch) {
        const index = next.findIndex((slot) => slot === null);
        if (index === -1) break;
        next[index] = URL.createObjectURL(file);
        added += 1;
      }
      if (added < batch.length) {
        setNotice("All 21 slots are full — remove an image before adding more.");
      }
      return next;
    });
    if (inputRef.current) inputRef.current.value = "";
  };

  const removeSlot = (index: number) => {
    setSlots((current) => {
      const next = [...current];
      const url = next[index];
      if (url?.startsWith("blob:")) URL.revokeObjectURL(url);
      next[index] = null;
      return next;
    });
    setNotice(null);
  };

  const clearAll = () => {
    setSlots((current) => {
      current.forEach((url) => {
        if (url?.startsWith("blob:")) URL.revokeObjectURL(url);
      });
      return Array(MAX_SLOTS).fill(null);
    });
    setNotice(null);
  };

  return (
    <div className="gallery-manager">
      <div className="gallery-toolbar">
        <p className="gallery-count">
          <b>{filled}</b> / {MAX_SLOTS} images
        </p>
        <div className="gallery-actions">
          <Button type="button" variant="outline" onClick={() => inputRef.current?.click()} disabled={remaining === 0}>
            <ImagePlus size={15} />
            Add images{remaining > 0 ? ` (${Math.min(BATCH_LIMIT, remaining)} max)` : ""}
          </Button>
          {filled > 0 && (
            <Button type="button" variant="ghost" onClick={clearAll}>
              <Trash2 size={15} />
              Clear all
            </Button>
          )}
        </div>
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          multiple
          className="sr-only"
          aria-label="Upload gallery images"
          onChange={(event) => handleFiles(event.target.files)}
        />
      </div>
      {notice && <p className="gallery-notice" role="status">{notice}</p>}
      <div className="gallery-grid">
        {slots.map((url, index) => (
          <figure key={index} className={url ? "gallery-slot filled" : "gallery-slot"}>
            {url ? (
              <>
                <img src={url} alt={`Gallery image ${index + 1}`} />
                <button type="button" className="gallery-remove" aria-label={`Remove image ${index + 1}`} onClick={() => removeSlot(index)}>
                  <X size={13} />
                </button>
              </>
            ) : (
              <span>{index + 1}</span>
            )}
          </figure>
        ))}
      </div>
    </div>
  );
}
