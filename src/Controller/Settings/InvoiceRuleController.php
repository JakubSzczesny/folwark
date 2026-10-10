<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Audit\AuditLogger;
use App\Entity\InvoiceRule;
use App\Form\InvoiceRuleType;
use App\Repository\ContractorRepository;
use App\Repository\InvoiceRuleRepository;
use App\Util\Nip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ustawienia/reguly')]
final class InvoiceRuleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('', name: 'app_settings_rules', methods: ['GET'])]
    public function index(InvoiceRuleRepository $rules, ContractorRepository $contractors): Response
    {
        $list = $rules->findBy([], ['contractorNip' => 'ASC']);
        $names = [];
        foreach ($list as $rule) {
            $names[$rule->getContractorNip()] = $contractors->findOneBy(['nip' => $rule->getContractorNip()])?->getName();
        }

        return $this->render('settings/rules/index.html.twig', ['rules' => $list, 'names' => $names]);
    }

    #[Route('/nowa', name: 'app_settings_rule_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $rule = new InvoiceRule();
        if ($nip = Nip::normalize($request->query->getString('nip'))) {
            $rule->setContractorNip($nip);
        }

        return $this->handle($rule, $request);
    }

    #[Route('/{id}', name: 'app_settings_rule_edit', methods: ['GET', 'POST'])]
    public function edit(InvoiceRule $rule, Request $request): Response
    {
        return $this->handle($rule, $request);
    }

    #[Route('/{id}/usun', name: 'app_settings_rule_delete', methods: ['POST'])]
    public function delete(InvoiceRule $rule, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('rule', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->audit->log('InvoiceRule', $rule->getId(), 'delete', ['nip' => $rule->getContractorNip()]);
        $this->em->remove($rule);
        $this->em->flush();

        return $this->redirectToRoute('app_settings_rules', [], Response::HTTP_SEE_OTHER);
    }

    private function handle(InvoiceRule $rule, Request $request): Response
    {
        $form = $this->createForm(InvoiceRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rule->setContractorNip((string) Nip::normalize($rule->getContractorNip()));
            $this->em->persist($rule);
            $this->em->flush();
            $this->audit->log('InvoiceRule', $rule->getId(), 'update', [
                'nip' => $rule->getContractorNip(),
                'costCenter' => $rule->getCostCenter()?->getName(),
                'costCategory' => $rule->getCostCategory()?->getName(),
                'active' => $rule->isActive(),
            ]);
            $this->em->flush();
            $this->addFlash('success', 'Zapisano regułę. Dotyczy nowych faktur z KSeF.');

            return $this->redirectToRoute('app_settings_rules', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('settings/rules/form.html.twig', ['form' => $form, 'rule' => $rule]);
    }
}
