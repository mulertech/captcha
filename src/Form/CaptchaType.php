<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Form;

use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use MulerTech\CaptchaBundle\Validator\ValidCaptcha;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CaptchaType extends AbstractType
{
    public function __construct(
        private readonly CaptchaGenerator $generator,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?object $cspNonceGenerator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $session = $this->requestStack->getSession();
        $captchaData = $this->generator->generate($session);

        $builder
            ->add('token', HiddenType::class, [
                'data' => $captchaData->token,
            ])
            ->add('answer', TextType::class, [
                'label' => 'captcha.answer_label',
                'attr' => [
                    'autocomplete' => 'off',
                    'inputmode' => 'numeric',
                    'placeholder' => '?',
                ],
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $token = $form->get('token')->getData() ?? '';

        $view->vars['captcha_image_url'] = $this->urlGenerator->generate(
            'mulertech_captcha_image',
            ['token' => $token],
        );

        $view->vars['captcha_refresh_url'] = $this->urlGenerator->generate(
            'mulertech_captcha_refresh',
        );

        $nonce = $options['csp_nonce'];

        if (null === $nonce && null !== $this->cspNonceGenerator && method_exists($this->cspNonceGenerator, 'getNonce')) {
            $nonce = $this->cspNonceGenerator->getNonce('main');
        }

        $view->vars['csp_nonce'] = $nonce;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'constraints' => [new ValidCaptcha()],
            'label' => false,
            'inherit_data' => false,
            'error_bubbling' => false,
            'translation_domain' => 'MulerTechCaptchaBundle',
            'csp_nonce' => null,
        ]);

        $resolver->setAllowedTypes('csp_nonce', ['null', 'string']);
    }

    public function getBlockPrefix(): string
    {
        return 'mulertech_captcha';
    }
}
