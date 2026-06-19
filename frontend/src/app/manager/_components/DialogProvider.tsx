"use client";

// Remplace les boîtes natives du navigateur (window.confirm / window.prompt)
// par des modales habillées avec la charte graphique du projet.

import { createContext, useCallback, useContext, useState } from "react";

type ConfirmOptions = {
  title?: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
};

type PromptOptions = {
  title?: string;
  label?: string;
  defaultValue?: string;
  confirmLabel?: string;
  cancelLabel?: string;
};

type DialogState =
  | { kind: "confirm"; options: ConfirmOptions; resolve: (value: boolean) => void }
  | { kind: "prompt"; options: PromptOptions; resolve: (value: string | null) => void }
  | null;

type DialogContextValue = {
  confirm: (options: ConfirmOptions) => Promise<boolean>;
  prompt: (options: PromptOptions) => Promise<string | null>;
};

const DialogContext = createContext<DialogContextValue | null>(null);

export function DialogProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<DialogState>(null);
  const [inputValue, setInputValue] = useState("");

  const confirm = useCallback((options: ConfirmOptions) => {
    return new Promise<boolean>((resolve) => {
      setState({ kind: "confirm", options, resolve });
    });
  }, []);

  const prompt = useCallback((options: PromptOptions) => {
    setInputValue(options.defaultValue ?? "");
    return new Promise<string | null>((resolve) => {
      setState({ kind: "prompt", options, resolve });
    });
  }, []);

  function settle(result: boolean | string | null) {
    if (!state) return;
    if (state.kind === "confirm") state.resolve(Boolean(result));
    else state.resolve(typeof result === "string" ? result : null);
    setState(null);
  }

  return (
    <DialogContext.Provider value={{ confirm, prompt }}>
      {children}
      {state && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
          onClick={() => settle(state.kind === "confirm" ? false : null)}
        >
          <div
            className="w-full max-w-sm rounded-lg border border-line bg-white p-5 shadow-lg"
            onClick={(e) => e.stopPropagation()}
          >
            <h3 className="mb-3 text-base font-semibold text-charcoal">
              {state.options.title ?? (state.kind === "confirm" ? "Confirmation" : "Renommer")}
            </h3>

            {state.kind === "confirm" ? (
              <p className="mb-5 text-sm text-muted">{state.options.message}</p>
            ) : (
              <>
                {state.options.label && (
                  <label className="mb-1 block text-xs font-semibold text-muted">
                    {state.options.label}
                  </label>
                )}
                <input
                  autoFocus
                  className="mb-5 w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:border-red"
                  value={inputValue}
                  onChange={(e) => setInputValue(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") settle(inputValue.trim());
                    if (e.key === "Escape") settle(null);
                  }}
                />
              </>
            )}

            <div className="flex justify-end gap-2">
              <button
                type="button"
                className="rounded-md border border-line px-4 py-2 text-sm font-semibold hover:border-charcoal"
                onClick={() => settle(state.kind === "confirm" ? false : null)}
              >
                {state.options.cancelLabel ?? "Annuler"}
              </button>
              <button
                type="button"
                className="rounded-md bg-red px-4 py-2 text-sm font-semibold text-white hover:bg-red-dark"
                onClick={() => settle(state.kind === "confirm" ? true : inputValue.trim())}
              >
                {state.options.confirmLabel ?? (state.kind === "confirm" ? "Confirmer" : "Renommer")}
              </button>
            </div>
          </div>
        </div>
      )}
    </DialogContext.Provider>
  );
}

export function useDialog() {
  const ctx = useContext(DialogContext);
  if (!ctx) throw new Error("useDialog must be used within DialogProvider");
  return ctx;
}
