<?php

namespace App\Controller;

use App\Entity\Path;
use App\Entity\User;
use App\Form\PathType;
use App\Form\PathSearchType;
use App\Repository\LocationRepository;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use App\Form\PathBookType;

class PathController extends AbstractController {

    /**
     * @Route("/path/create", name="new_path")
     * @IsGranted("ROLE_USER")
     * @param Request $request
     * @param LocationRepository $locationRepository
     * @return RedirectResponse|Response
     */
    public function create(Request $request, LocationRepository $locationRepository) {

        // If there are no Location yet, let's redirect to the location creation.
        if (!$locationRepository->findAll()) {
            return $this->redirectToRoute("new_location");
        }

        $path = new Path();
        $form = $this->createForm(PathType::class, $path);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();

            $user = $this->getUser();
            $path->setDriver($user);
            $path->setLeftSeats($path->getSeats());

            $entityManager->persist($path);
            $entityManager->flush();

            $this->addFlash('success', 'Trajet correctement enregistré.');
        }

        return $this->render('path/index.html.twig', [
                    'createPathForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/path/search", name="search_path")
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function search(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $path = new Path();
        $path->setStartTime(new \DateTime());
        $form = $this->createForm(PathSearchType::class, $path);
        $form->handleRequest($request);

        if ($path->getLeftSeats() === null) {
            $path->setLeftSeats(1);
        }

        $formBook = $this->createForm(PathBookType::class, null, []);

        $paths = $em->getRepository(Path::class)->findForSearch($path);

        return $this->render('path/search.html.twig', [
                    'form' => $form->createView(),
                    'formBookObject' => $formBook,
                    'paths' => $paths
        ]);
    }

    /**
     * @Route("/path/{id}/book", name="book_path")
     * @IsGranted("ROLE_USER")
     * @param Request $request
     * @param Path path
     * @return RedirectResponse|Response
     */
    public function book(Request $request, Path $path) {
        $em = $this->getDoctrine()->getManager();

        $user = $em->getRepository(User::class)->find($this->getUser()->getId());

        $numberPassager = $request->request->get('path_book')['numberPassenger'];

        $path->setLeftSeats($path->getLeftSeats() - $numberPassager);
        $path->addPassenger($user);

        $em->persist($path);
        $em->flush();

        $this->addFlash('success', 'Réservation correctement enregistrée.');
        
        return $this->redirect($this->generateUrl('account'));
    }

    /**
     * @Route("/path/{id}/show", name="show_path")
     * @param Request $request
     * @param Path $path
     * @return RedirectResponse|Response
     */
    public function show(Request $request, Path $path) {

        $form = $this->createForm(PathBookType::class, null, [
            "action" => $this->generateUrl('book_path', ["id" => $path->getId()])
        ]);

        return $this->render('path/show.html.twig', [
                    'path' => $path,
                    'form' => $form->createView()
        ]);
    }
    
    /**
     * @Route("/path/{id}/cancel", name="cancel_book_path")
     * @IsGranted("ROLE_USER")
     * @param Path $path
     * @return RedirectResponse|Response
     */
    public function cancelBook(Path $path) {
        $em = $this->getDoctrine()->getManager();
        
        $user = $this->getUser();
        
        if ($user->getId() == $path->getDriver()->getId()) {
            $em->remove($path);
            
            $this->addFlash('success', 'Annulation du trajet correctemet enregistrée.');
            
        } else if ($path->getPassengers()->contains($user)) {
            $path->removePassenger($user);
            $path->setLeftSeats($path->getLeftSeats() + 1);
            $em->persist($path);
            
            $this->addFlash('success', 'Annulation de la réservation correctement enregistrée.');
        } else {
            throw $this->createAccessDeniedException();
        }
        
        $em->flush();
        
        return $this->redirect($this->generateUrl('account'));
    }

}
