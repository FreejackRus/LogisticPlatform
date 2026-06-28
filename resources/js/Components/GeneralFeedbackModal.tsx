import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useToast } from '@/hooks/UseToast';
import { router } from '@inertiajs/react';
import { CheckCircle, Send } from 'lucide-react';
import { useState } from 'react';

interface GeneralFeedbackModalProps {
    isOpen: boolean;
    onClose: () => void;
    userEmail: string;
}

export default function GeneralFeedbackModal({
    isOpen,
    onClose,
    userEmail,
}: GeneralFeedbackModalProps) {
    const [message, setMessage] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<{ feedback?: string }>({});
    const { toast } = useToast();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isSubmitting) return;

        // Reset errors
        setErrors({});

        // Basic validation
        const newErrors: { feedback?: string } = {};
        if (!message) {
            newErrors.feedback = 'Введите сообщение';
        } else if (message.length < 10) {
            newErrors.feedback = 'Сообщение должно быть не короче 10 символов';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setIsSubmitting(true);

        router.post(
            route('feedback.submit'),
            {
                email: userEmail,
                feedback: message,
            },
            {
                onSuccess: () => {
                    setMessage('');
                    setErrors({});
                    onClose();

                    // Show success toast
                    toast({
                        title: 'Спасибо за обратную связь!',
                        description: (
                            <>
                                <CheckCircle
                                    className="mr-2 inline h-4 w-4"
                                    color="green"
                                />
                                Мы получили сообщение и рассмотрим его.
                            </>
                        ),
                    });
                },
                onError: (errors) => {
                    setErrors(errors);
                    toast({
                        title: 'Не удалось отправить',
                        description:
                            'При отправке сообщения произошла ошибка. Попробуйте еще раз.',
                        variant: 'destructive',
                    });
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleClose = () => {
        if (isSubmitting) return;
        setMessage('');
        setErrors({});
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Обратная связь</DialogTitle>
                    <DialogDescription>
                        Напишите идею, вопрос или проблему по работе сервиса.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="message">Сообщение</Label>
                            <Textarea
                                id="message"
                                placeholder="Опишите, что стоит улучшить, исправить или добавить..."
                                value={message}
                                onChange={(e) => setMessage(e.target.value)}
                                disabled={isSubmitting}
                                rows={4}
                                className={
                                    errors.feedback ? 'border-red-500' : ''
                                }
                            />
                            {errors.feedback && (
                                <p className="text-sm text-red-500">
                                    {errors.feedback}
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={isSubmitting}
                        >
                            Отмена
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent" />
                                    Отправка...
                                </>
                            ) : (
                                <>
                                    <Send className="mr-2 h-4 w-4" />
                                    Отправить
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
