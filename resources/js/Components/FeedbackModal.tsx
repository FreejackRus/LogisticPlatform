import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useToast } from '@/hooks/UseToast';
import { router } from '@inertiajs/react';
import { CheckCircle, Send } from 'lucide-react';
import { useState } from 'react';

interface FeedbackModalProps {
    isOpen: boolean;
    onClose: () => void;
}

export default function FeedbackModal({ isOpen, onClose }: FeedbackModalProps) {
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<{ email?: string; feedback?: string }>(
        {},
    );
    const { toast } = useToast();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isSubmitting) return;

        // Reset errors
        setErrors({});

        // Basic validation
        const newErrors: { email?: string; feedback?: string } = {};
        if (!email) {
            newErrors.email = 'Введите email';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            newErrors.email = 'Введите корректный email';
        }

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

        // Add "Enterprise Inquiry" prefix to the message
        const enterpriseMessage = `Запрос от компании: ${message}`;

        router.post(
            route('feedback.submit'),
            {
                email: email,
                feedback: enterpriseMessage,
            },
            {
                onSuccess: () => {
                    setEmail('');
                    setMessage('');
                    setErrors({});
                    onClose();

                    // Show success toast
                    toast({
                        title: 'Спасибо за запрос!',
                        description: (
                            <>
                                <CheckCircle
                                    className="mr-2 inline h-4 w-4"
                                    color="green"
                                />
                                Мы получили сообщение и свяжемся с вами.
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
        setEmail('');
        setMessage('');
        setErrors({});
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Запрос от компании</DialogTitle>
                    <DialogDescription>
                        Опишите задачу, размер команды и нужные возможности сервиса.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Эл. почта</Label>
                            <Input
                                id="email"
                                type="email"
                                placeholder="your@company.com"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                disabled={isSubmitting}
                                className={errors.email ? 'border-red-500' : ''}
                            />
                            {errors.email && (
                                <p className="text-sm text-red-500">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="message">Сообщение</Label>
                            <Textarea
                                id="message"
                                placeholder="Расскажите, что нужно вашей компании..."
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
